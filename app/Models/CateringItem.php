<?php

namespace App\Models;

use App\Enums\CateringUnit;
use Database\Factories\CateringItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A dish the kitchen caters, quoted in bulk.
 *
 * Separate from the restaurant's menu items: the same curry sells as a plate
 * over the counter and by the kilo at a wedding, at different prices.
 */
class CateringItem extends Model
{
    /** @use HasFactory<CateringItemFactory> */
    use HasFactory;

    protected $fillable = ['name', 'default_unit', 'price', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'default_unit' => CateringUnit::class,
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return HasMany<CateringBookingItem, $this> */
    public function bookingItems(): HasMany
    {
        return $this->hasMany(CateringBookingItem::class);
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
