<?php

use App\Enums\OrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Clears out the empty orders the order screen used to leave behind.
 *
 * An order was created the moment a sale type or a table was tapped, so
 * opening the order screen only to check a price or search the menu left an
 * open order with nothing on it — about a thousand of them by the time it was
 * noticed. The POS now opens an order with its first item, so no more arrive
 * that way; this removes the ones already there.
 *
 * Only open orders with no items and no payments go. Nothing was sold on them,
 * so no figure anywhere changes, and a table one was holding is freed. An empty
 * cancelled order is left alone: somebody chose to cancel it, and a cancelled
 * order stays on the books.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')
            ->where('status', OrderStatus::Open->value)
            ->whereNotExists(fn (Builder $q) => $q->selectRaw('1')
                ->from('order_items')
                ->whereColumn('order_items.order_id', 'orders.id'))
            ->whereNotExists(fn (Builder $q) => $q->selectRaw('1')
                ->from('payments')
                ->whereColumn('payments.order_id', 'orders.id'))
            ->delete();
    }

    public function down(): void
    {
        // Nothing to put back: the rows held no items, no payments and no money.
    }
};
