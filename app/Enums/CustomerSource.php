<?php

namespace App\Enums;

/**
 * How a customer first came to be known to the restaurant.
 *
 * Recorded once, when the record is created, and never rewritten — a caller
 * who later eats in is still someone the restaurant met over the phone, and
 * rewriting that would destroy the only answer to "where do our regulars come
 * from?".
 */
enum CustomerSource: string
{
    case WalkIn = 'walk_in';
    case DineIn = 'dine_in';
    case Phone = 'phone';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::WalkIn => 'Walk in',
            self::DineIn => 'Dine in',
            self::Phone => 'Phone call',
            self::Manual => 'Added manually',
        };
    }

    /** Where a customer captured on this kind of order belongs. */
    public static function fromOrderType(OrderType $type): self
    {
        return match ($type) {
            OrderType::DineIn => self::DineIn,
            OrderType::Takeaway => self::WalkIn,
            OrderType::PhoneTakeaway => self::Phone,
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::WalkIn => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
            self::DineIn => 'bg-sky-100 text-sky-800 dark:bg-sky-500/15 dark:text-sky-300',
            self::Phone => 'bg-violet-100 text-violet-800 dark:bg-violet-500/15 dark:text-violet-300',
            self::Manual => 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
        };
    }
}
