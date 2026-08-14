<?php

namespace App\Enums;

/**
 * Payment methods accepted at checkout.
 *
 * Adding a method later is a two step job: add the case here, and decide in
 * {@see self::requiresTendered()} whether the cashier types in an amount
 * received (cash-like) or not (terminal-like). The checkout screen and the
 * reports build themselves from these cases.
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Cash-like methods ask for the amount handed over so we can show change. */
    public function requiresTendered(): bool
    {
        return $this === self::Cash;
    }

    public function icon(): string
    {
        return match ($this) {
            self::Cash => 'banknotes',
            self::Card => 'credit-card',
        };
    }
}
