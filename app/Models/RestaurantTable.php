<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use Database\Factories\RestaurantTableFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A physical table in the dining room. Backed by the `tables` table; the class
 * is named RestaurantTable so it never reads like a database table in code.
 *
 * Occupancy is not stored. A table is occupied exactly when it has an open
 * dine-in order, which keeps the two from ever drifting apart.
 */
class RestaurantTable extends Model
{
    /** @use HasFactory<RestaurantTableFactory> */
    use HasFactory;

    protected $table = 'tables';

    protected $fillable = ['name', 'seats', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'seats' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'table_id');
    }

    /** The one open dine-in order sitting at this table, if any. */
    public function activeOrder(): HasOne
    {
        return $this->hasOne(Order::class, 'table_id')
            ->where('type', OrderType::DineIn)
            ->where('status', OrderStatus::Open);
    }

    public function isOccupied(): bool
    {
        return $this->activeOrder !== null;
    }

    public function isAvailable(): bool
    {
        return ! $this->isOccupied();
    }

    /** @param Builder<$this> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param Builder<$this> $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }
}
