<?php

namespace App\Http\Controllers\Pos;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Models\Order;
use App\Services\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(private readonly CheckoutService $checkout) {}

    public function show(Order $order): View|RedirectResponse
    {
        $this->authorize('checkout', $order);

        $order->load(['items', 'table']);

        return view('pos.checkout', [
            'order' => $order,
            'methods' => PaymentMethod::cases(),
        ]);
    }

    /**
     * Record the payment and complete the order. Everything that matters
     * happens inside {@see CheckoutService::checkout()}'s transaction.
     */
    public function store(CheckoutRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('checkout', $order);

        $this->checkout->checkout(
            $order,
            $request->paymentMethod(),
            Auth::user(),
            $request->filled('tendered') ? (float) $request->validated('tendered') : null,
            $request->validated('reference'),
        );

        return redirect()->route('orders.receipt', $order)
            ->with('status', "Order {$order->order_number} completed.");
    }
}
