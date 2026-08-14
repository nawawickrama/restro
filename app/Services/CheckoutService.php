<?php

namespace App\Services;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\PosOperationException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Taking the money and closing the order.
 *
 * Business rules 12 and 14: an order is only completed once a payment covering
 * its total has been recorded, and both happen inside one transaction so the
 * system can never end up with a completed order that nobody paid for.
 */
class CheckoutService
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly SettingsService $settings,
    ) {}

    public function checkout(
        Order $order,
        PaymentMethod $method,
        User $user,
        ?float $tendered = null,
        ?string $reference = null,
    ): Payment {
        return DB::transaction(function () use ($order, $method, $user, $tendered, $reference): Payment {
            // Re-read under a lock: the order may have been paid or cancelled on
            // another terminal while this checkout screen was open.
            $order = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if (! $order->isEditable()) {
                throw PosOperationException::orderLocked($order->status->value);
            }

            if (! $order->items()->exists()) {
                throw PosOperationException::emptyOrder();
            }

            // Business rule 13, enforced at the point it actually matters: a
            // phone order may be built before the customer gives their number,
            // but it cannot be completed without one.
            if ($order->type === OrderType::PhoneTakeaway && blank($order->customer_phone)) {
                throw PosOperationException::missingCustomerPhone();
            }

            // Totals are recomputed from the lines here, so the amount charged
            // is never whatever a form field happened to say.
            $this->orders->recalculate($order);

            $total = round((float) $order->total, 2);

            if ($method->requiresTendered()) {
                $tendered = round((float) $tendered, 2);

                if ($tendered + 0.001 < $total) {
                    throw PosOperationException::insufficientPayment(
                        $this->settings->formatMoney($total),
                        $this->settings->formatMoney($tendered),
                    );
                }
            } else {
                $tendered = null;
            }

            $payment = $order->payments()->create([
                'user_id' => $user->id,
                'method' => $method,
                'amount' => $total,
                'tendered' => $tendered,
                'change_amount' => $tendered === null ? 0 : round($tendered - $total, 2),
                'reference' => $reference,
                'paid_at' => now(),
            ]);

            $order->forceFill([
                'payment_status' => PaymentStatus::Paid,
                'status' => OrderStatus::Completed,
                'completed_at' => now(),
                // A phone order that has been paid for has been handed over.
                'fulfillment_status' => $order->type === OrderType::PhoneTakeaway
                    ? FulfillmentStatus::Collected
                    : $order->fulfillment_status,
            ])->save();

            return $payment;
        });
    }
}
