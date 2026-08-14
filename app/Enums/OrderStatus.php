<?php

namespace App\Enums;

/**
 * The order lifecycle. Deliberately tiny: an order is being worked on,
 * it is finished, or it was thrown away.
 *
 * Phone orders track their kitchen/collection stage separately in
 * {@see FulfillmentStatus} so that "is this order still open?" stays a
 * single, unambiguous question everywhere in the app.
 */
enum OrderStatus: string
{
    case Open = 'open';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Open orders are the only ones that may still be edited. */
    public function isEditable(): bool
    {
        return $this === self::Open;
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Open => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
            self::Completed => 'bg-slate-200 text-slate-700 dark:bg-slate-500/20 dark:text-slate-300',
            self::Cancelled => 'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-300',
        };
    }
}
