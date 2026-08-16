<?php

namespace App\Http\Controllers\Pos;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderType;
use App\Http\Controllers\Controller;
use App\Http\Requests\OrderCustomerRequest;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The front door of the POS: pick a sale type, then get taken straight to an
 * order screen. Three taps at most from opening the app to adding food.
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
     * Tapping a table either opens its running order or starts a new one, so a
     * cashier never has to think about which of the two they are doing.
     */
    public function selectTable(RestaurantTable $table): RedirectResponse
    {
        $existing = $table->activeOrder()->first();

        if ($existing) {
            return redirect()->route('pos.orders.show', $existing);
        }

        $this->authorize('create', Order::class);

        $order = $this->orders->startDineIn($table, $this->user());

        return redirect()->route('pos.orders.show', $order);
    }

    public function storeTakeaway(): RedirectResponse
    {
        $this->authorize('create', Order::class);

        $order = $this->orders->startTakeaway($this->user());

        return redirect()->route('pos.orders.show', $order);
    }

    /**
     * A phone order opens empty and goes straight to the order screen: on a
     * call the customer reads out their food first and gives their number
     * afterwards. {@see self::updateCustomer()} captures the details.
     */
    public function storePhoneOrder(): RedirectResponse
    {
        $this->authorize('create', Order::class);

        $order = $this->orders->startPhoneOrder($this->user());

        return redirect()->route('pos.orders.show', $order);
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

    private function user(): User
    {
        return Auth::user();
    }
}
