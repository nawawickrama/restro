<?php

namespace App\Enums;

/**
 * Where a phone takeaway order is in the kitchen -> counter journey.
 * Only phone orders use this; every other order type leaves it null.
 */
enum FulfillmentStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Collected = 'collected';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
            self::Ready => 'bg-sky-100 text-sky-800 dark:bg-sky-500/15 dark:text-sky-300',
            self::Collected => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
        };
    }
}
