<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A business rule said no.
 *
 * These are expected, explainable refusals ("that table is already busy"),
 * not bugs. They are rendered back to the cashier as a plain message by the
 * handler registered in bootstrap/app.php.
 */
class PosOperationException extends RuntimeException
{
    public static function tableOccupied(string $tableName): self
    {
        return new self("{$tableName} already has an open order.");
    }

    public static function tableInactive(string $tableName): self
    {
        return new self("{$tableName} is not available for orders.");
    }

    public static function itemUnavailable(string $itemName): self
    {
        return new self("{$itemName} is not available and cannot be added to an order.");
    }

    public static function orderLocked(string $status): self
    {
        return new self("This order is {$status} and can no longer be changed.");
    }

    public static function emptyOrder(): self
    {
        return new self('Add at least one item before checking out.');
    }

    public static function insufficientPayment(string $required, string $received): self
    {
        return new self("Payment of {$received} does not cover the total of {$required}.");
    }

    public static function discountTooLarge(): self
    {
        return new self('The discount cannot be greater than the order subtotal.');
    }

    public static function notATableOrder(): self
    {
        return new self('Only dine-in orders can be moved between tables.');
    }

    /** Business rule 13: a phone order has to be reachable before it is paid for. */
    public static function missingCustomerPhone(): self
    {
        return new self('Add the customer\'s mobile number before completing this phone order.');
    }
}
