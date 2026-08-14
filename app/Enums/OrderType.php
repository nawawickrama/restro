<?php

namespace App\Enums;

enum OrderType: string
{
    case DineIn = 'dine_in';
    case Takeaway = 'takeaway';
    case PhoneTakeaway = 'phone_takeaway';

    public function label(): string
    {
        return match ($this) {
            self::DineIn => 'Dine In',
            self::Takeaway => 'Takeaway',
            self::PhoneTakeaway => 'Phone Order',
        };
    }

    /** Dine-in orders occupy a table for as long as they stay open. */
    public function usesTable(): bool
    {
        return $this === self::DineIn;
    }

    /** Phone orders capture customer details and track a fulfillment stage. */
    public function requiresCustomer(): bool
    {
        return $this === self::PhoneTakeaway;
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::DineIn => 'bg-sky-100 text-sky-800 dark:bg-sky-500/15 dark:text-sky-300',
            self::Takeaway => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
            self::PhoneTakeaway => 'bg-violet-100 text-violet-800 dark:bg-violet-500/15 dark:text-violet-300',
        };
    }
}
