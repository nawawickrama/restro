<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line on an order.
 *
 * `name` and `unit_price` are snapshots taken when the item was added. Repricing
 * or renaming the menu afterwards must never change what a past order says.
 */
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id', 'menu_item_id', 'name', 'unit_price', 'quantity', 'line_total', 'note',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Null once the menu item has been deleted; the snapshot above still stands. */
    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    /** Recalculate the line from its own stored price and quantity. */
    public function recalculate(): void
    {
        $this->line_total = round((float) $this->unit_price * $this->quantity, 2);
    }
}
