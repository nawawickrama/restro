<?php

namespace App\Policies;

use App\Enums\OrderType;
use App\Models\Order;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Auth\Access\Response;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::VIEW_ORDERS);
    }

    public function view(User $user, Order $order): bool
    {
        return $user->can(Permissions::VIEW_ORDERS);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::CREATE_ORDERS);
    }

    /**
     * Business rules 1 and 2: completed and cancelled orders are frozen.
     * Everything that mutates an order's contents goes through here.
     */
    public function update(User $user, Order $order): Response
    {
        if (! $user->can(Permissions::EDIT_ORDERS)) {
            return Response::deny('You are not allowed to edit orders.');
        }

        return $order->isEditable()
            ? Response::allow()
            : Response::deny("This order is {$order->status->value} and can no longer be edited.");
    }

    public function cancel(User $user, Order $order): Response
    {
        if (! $user->can(Permissions::CANCEL_ORDERS)) {
            return Response::deny('You are not allowed to cancel orders.');
        }

        return $order->isEditable()
            ? Response::allow()
            : Response::deny("This order is already {$order->status->value}.");
    }

    public function checkout(User $user, Order $order): Response
    {
        if (! $user->can(Permissions::CHECKOUT_ORDERS)) {
            return Response::deny('You are not allowed to take payments.');
        }

        if (! $order->isEditable()) {
            return Response::deny("This order is already {$order->status->value}.");
        }

        if (! $order->items()->exists()) {
            return Response::deny('Add at least one item before checking out.');
        }

        // Business rule 13: a phone order needs a number before it can be paid for.
        if ($order->type === OrderType::PhoneTakeaway && blank($order->customer_phone)) {
            return Response::deny('Add the customer\'s mobile number before checking out.');
        }

        return Response::allow();
    }

    /** Business rule 10: discounts are a privileged action. */
    public function applyDiscount(User $user, Order $order): Response
    {
        if (! $user->can(Permissions::APPLY_DISCOUNTS)) {
            return Response::deny('You are not allowed to apply discounts.');
        }

        return $order->isEditable()
            ? Response::allow()
            : Response::deny("This order is {$order->status->value} and can no longer be edited.");
    }

    public function printReceipt(User $user, Order $order): bool
    {
        return $user->can(Permissions::PRINT_RECEIPTS);
    }
}
