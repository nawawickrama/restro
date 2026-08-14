<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Carbon;

/**
 * Human-readable, per-day sequential order numbers: ORD-20260811-0001.
 *
 * Cashiers read these aloud, so they need to be short and obviously ordered.
 * The unique index on orders.order_number is the real guard; callers retry on
 * collision (see {@see OrderService::createOrder()}).
 */
class OrderNumberGenerator
{
    public function generate(?Carbon $date = null): string
    {
        $date ??= now();
        $prefix = 'ORD-'.$date->format('Ymd').'-';

        $last = Order::query()
            ->where('order_number', 'like', $prefix.'%')
            ->orderByDesc('order_number')
            ->value('order_number');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
