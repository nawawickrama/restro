<?php

namespace App\Enums;

/**
 * Where a catering booking stands.
 *
 * Kept to four, because the calendar has to be readable across a room: an
 * enquiry that may not happen, a job that is definitely on, one that has been
 * delivered, and one that fell through.
 */
enum CateringStatus: string
{
    case Enquiry = 'enquiry';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Cancelled bookings stop counting towards a day's workload. */
    public function isLive(): bool
    {
        return $this !== self::Cancelled;
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Enquiry => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
            self::Confirmed => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
            self::Completed => 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
            self::Cancelled => 'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-300',
        };
    }

    /** A solid colour for the dot on the calendar. */
    public function dotClasses(): string
    {
        return match ($this) {
            self::Enquiry => 'bg-amber-500',
            self::Confirmed => 'bg-emerald-500',
            self::Completed => 'bg-slate-400',
            self::Cancelled => 'bg-rose-500',
        };
    }
}
