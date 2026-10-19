<?php

namespace App\Http\Controllers\Pos;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderType;
use App\Http\Controllers\Controller;
use App\Http\Requests\OrderCustomerRequest;
use App\Http\Requests\StoreOrderItemRequest;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Services\OrderService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The front door of the POS: pick a sale type, then get taken straight to an
 * order screen. Three taps at most from opening the app to adding food.
 *
 * The order itself is only created by the first item tapped on that screen,
 * through the store methods here, so opening it to check the menu writes
 * nothing.
 */
class PosController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    /** DINE IN / TAKEAWAY / PHONE ORDER, plus the live table plan. */
    public function home(): View
    {
        $tables = RestaurantTable::query()
            ->active()
            ->ordered()
            ->with(['activeOrder' => fn ($q) => $q->withSum('items as items_count', 'quantity')])
            ->get();

        // Only the item count is shown, so counting in SQL beats loading every
        // line of every open order just to add them up in PHP.
        $openOrders = Order::query()
            ->open()
            ->whereIn('type', [OrderType::Takeaway, OrderType::PhoneTakeaway])
            ->withSum('items as items_quantity', 'quantity')
            ->latest()
            ->get();

        return view('pos.home', [
            'tables' => $tables,
            'openOrders' => $openOrders,
            'openOrdersPayload' => $this->searchableOrders($openOrders),
        ]);
    }

    /**
     * The menu on its own, for a price or "do you have…?" at the counter.
     *
     * Read-only and a screen of its own, so checking the menu is plainly not
     * the start of a sale: nothing here creates an order.
     */
    public function menu(): View
    {
        return view('pos.menu', ['categories' => Category::onTheMenu()]);
    }

    /**
     * The first item tapped at a free table, which is what opens its order and
     * occupies it. {@see OrderScreenController::table()} shows the screen it is
     * tapped on.
     */
    public function storeAtTable(StoreOrderItemRequest $request, RestaurantTable $table): JsonResponse|RedirectResponse
    {
        $this->authorize('create', Order::class);

        return $this->open($request, fn (): Order => $this->orders->startDineIn($table, $this->user()));
    }

    public function storeTakeaway(StoreOrderItemRequest $request): JsonResponse|RedirectResponse
    {
        $this->authorize('create', Order::class);

        return $this->open($request, fn (): Order => $this->orders->startTakeaway($this->user()));
    }

    /**
     * A phone order opens with the first dish the caller reads out; their
     * number is taken afterwards by {@see self::updateCustomer()}.
     */
    public function storePhoneOrder(StoreOrderItemRequest $request): JsonResponse|RedirectResponse
    {
        $this->authorize('create', Order::class);

        return $this->open($request, fn (): Order => $this->orders->startPhoneOrder($this->user()));
    }

    public function updateCustomer(OrderCustomerRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('update', $order);

        $this->orders->setCustomer($order, $request->validated());

        return back()->with('status', 'Customer details saved.');
    }

    /** Phone orders only: pending -> ready -> collected. */
    public function updateFulfillment(Order $order, string $status): RedirectResponse
    {
        $this->authorize('update', $order);

        $this->orders->setFulfillmentStatus($order, FulfillmentStatus::from($status));

        return back()->with('status', "Order {$order->order_number} marked as ".ucfirst($status).'.');
    }

    /**
     * The open orders in the shape the home screen's search box needs.
     *
     * Built here rather than in Blade so the view stays markup, and so the
     * haystack is lower-cased once per order instead of on every keystroke.
     *
     * @param  Collection<int, Order>  $orders
     */
    private function searchableOrders(Collection $orders): Collection
    {
        return $orders->map(function (Order $order): array {
            $items = (int) $order->items_quantity;

            return [
                'id' => $order->id,
                'url' => route('pos.orders.show', $order),
                'number' => $order->order_number,
                'type_label' => $order->type->label(),
                'type_classes' => $order->type->badgeClasses(),
                'customer' => $order->customerLabel(),
                'phone' => $order->customer_phone,
                'items' => $items.' '.Str::plural('item', $items),
                'total' => money($order->total),
                'fulfillment_label' => $order->fulfillment_status?->label(),
                'fulfillment_classes' => $order->fulfillment_status?->badgeClasses(),

                // Everything a cashier might type at the counter.
                'haystack' => mb_strtolower(implode(' ', array_filter([
                    $order->order_number,
                    $order->customerLabel(),
                    $order->customer_phone,
                    $order->type->label(),
                ]))),

                // Digits only, so a number typed without spaces still matches
                // one that was written down with them.
                'phone_digits' => preg_replace('/\D/', '', (string) $order->customer_phone),
            ];
        })->values();
    }

    /**
     * Open an order by its first item and send the screen on to it.
     *
     * The order screen calls this over fetch, so it answers with where to go
     * rather than a redirect the browser would follow out of sight.
     *
     * @param  Closure(): Order  $start
     */
    private function open(StoreOrderItemRequest $request, Closure $start): JsonResponse|RedirectResponse
    {
        $order = $this->orders->startWith(
            $start,
            MenuItem::query()->findOrFail($request->integer('menu_item_id')),
            $request->integer('quantity') ?: 1,
            $request->input('note'),
        );

        $url = route('pos.orders.show', $order);

        return $request->expectsJson()
            ? response()->json(['redirect' => $url], 201)
            : redirect($url);
    }

    private function user(): User
    {
        return Auth::user();
    }
}
