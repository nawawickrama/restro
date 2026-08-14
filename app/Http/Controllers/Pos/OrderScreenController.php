<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderItemRequest;
use App\Http\Requests\UpdateOrderItemRequest;
use App\Http\Resources\OrderResource;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RestaurantTable;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The order screen and the small JSON endpoints behind it.
 *
 * Item taps go over fetch so the screen never reloads mid-service; each one
 * returns the recalculated order, which is the only source of totals.
 */
class OrderScreenController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function show(Order $order): View|RedirectResponse
    {
        $this->authorize('view', $order);

        // A finished order has nothing left to edit; show the receipt instead.
        if (! $order->isEditable()) {
            return redirect()->route('orders.show', $order);
        }

        $order->load(['items', 'table']);

        $categories = Category::query()
            ->active()
            ->ordered()
            ->with(['activeMenuItems'])
            ->get()
            ->filter(fn (Category $category) => $category->activeMenuItems->isNotEmpty())
            ->values();

        $tables = $order->type->usesTable()
            ? RestaurantTable::query()->active()->ordered()->with('activeOrder')->get()
            : collect();

        return view('pos.order', compact('order', 'categories', 'tables'));
    }

    public function storeItem(StoreOrderItemRequest $request, Order $order): JsonResponse
    {
        $this->authorize('update', $order);

        $this->orders->addItem(
            $order,
            MenuItem::query()->findOrFail($request->integer('menu_item_id')),
            $request->integer('quantity') ?: 1,
            $request->input('note'),
        );

        return $this->orderJson($order);
    }

    public function updateItem(UpdateOrderItemRequest $request, Order $order, OrderItem $item): JsonResponse
    {
        $this->authorize('update', $order);
        abort_unless($item->order_id === $order->id, 404);

        if ($request->has('quantity')) {
            $this->orders->setQuantity($order, $item, $request->integer('quantity'));
        }

        if ($request->has('note')) {
            $this->orders->setItemNote($order, $item, $request->input('note'));
        }

        return $this->orderJson($order);
    }

    public function destroyItem(Order $order, OrderItem $item): JsonResponse
    {
        $this->authorize('update', $order);
        abort_unless($item->order_id === $order->id, 404);

        $this->orders->removeItem($order, $item);

        return $this->orderJson($order);
    }

    /** Business rule 10: gated by its own permission, not by edit rights. */
    public function applyDiscount(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('applyDiscount', $order);

        $validated = $request->validate([
            'discount_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $this->orders->applyDiscount($order, (float) $validated['discount_amount']);

        return back()->with('status', 'Discount updated.');
    }

    /** Move an open dine-in order to a different table. */
    public function moveTable(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('update', $order);

        $validated = $request->validate([
            'table_id' => ['required', 'integer', 'exists:tables,id'],
        ]);

        $this->orders->moveToTable($order, RestaurantTable::query()->findOrFail($validated['table_id']));

        return back()->with('status', 'Order moved.');
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('cancel', $order);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $this->orders->cancel($order, $validated['reason'] ?? null);

        return redirect()->route('pos.home')->with('status', "Order {$order->order_number} cancelled.");
    }

    /** Park the order and go back to the POS home screen. */
    public function hold(Order $order): RedirectResponse
    {
        $this->authorize('view', $order);

        return redirect()->route('pos.home')
            ->with('status', "Order {$order->order_number} is still open.");
    }

    private function orderJson(Order $order): JsonResponse
    {
        return response()->json([
            'order' => new OrderResource($order->fresh(['items', 'table'])),
        ]);
    }
}
