<?php

namespace App\Models;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'order_number', 'type', 'status', 'fulfillment_status', 'payment_status',
        'table_id', 'user_id', 'customer_name', 'customer_phone', 'note',
        'subtotal', 'discount_amount', 'tax_amount', 'total',
        'completed_at', 'cancelled_at',
    ];

    // Note: `active_table_id` is a generated column maintained by MySQL from
    // status/type/table_id. It is deliberately absent from $fillable and must
    // never be written to.

    protected function casts(): array
    {
        return [
            'type' => OrderType::class,
            'status' => OrderStatus::class,
            'fulfillment_status' => FulfillmentStatus::class,
            'payment_status' => PaymentStatus::class,
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return BelongsTo<RestaurantTable, $this> */
    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    public function isPaid(): bool
    {
        return $this->payment_status === PaymentStatus::Paid;
    }

    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    /** Total already settled across every payment on this order. */
    public function paidAmount(): float
    {
        return (float) $this->payments->sum('amount');
    }

    public function itemCount(): int
    {
        return (int) $this->items->sum('quantity');
    }

    /**
     * What to show in the "table / customer" column of a listing.
     *
     * A phone caller often gives a number and no name, so the number stands in
     * for one — it is what the counter will use to find them anyway. Dine-in
     * keeps the table as its identity and appends a name only when one was
     * taken, since the table is how staff refer to it.
     */
    public function customerLabel(): string
    {
        $name = trim((string) $this->customer_name);

        // Eloquent's Model declares `protected $table` for the database table
        // name, so inside this class `$this->table` is that string, not the
        // relationship — it reads as null and every dine-in order would be
        // labelled "No table". From outside the model `$order->table` is fine,
        // because __get only runs for inaccessible properties.
        $diningTable = $this->getRelationValue('table');

        return match ($this->type) {
            OrderType::DineIn => trim(($diningTable?->name ?? 'No table').($name !== '' ? " · {$name}" : '')),
            OrderType::PhoneTakeaway => $name !== '' ? $name : ($this->customer_phone ?: 'Phone customer'),
            OrderType::Takeaway => $name !== '' ? $name : 'Walk in',
        };
    }

    /** True once any customer detail has been recorded. */
    public function hasCustomerDetails(): bool
    {
        return filled($this->customer_name) || filled($this->customer_phone);
    }

    /** Business rule 13: a phone order cannot be completed without a number. */
    public function needsCustomerPhone(): bool
    {
        return $this->type === OrderType::PhoneTakeaway && blank($this->customer_phone);
    }

    /** @param Builder<$this> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', OrderStatus::Open);
    }

    /** @param Builder<$this> $query */
    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', OrderStatus::Completed);
    }

    /** @param Builder<$this> $query */
    public function scopeOfType(Builder $query, OrderType $type): void
    {
        $query->where('type', $type);
    }

    /**
     * @param  Builder<$this>  $query
     *
     * Expressed as a range rather than whereDate(): wrapping created_at in a
     * function stops MySQL using its index and turns this into a table scan.
     */
    public function scopeToday(Builder $query): void
    {
        $query->whereBetween('created_at', [today()->startOfDay(), today()->endOfDay()]);
    }
}
