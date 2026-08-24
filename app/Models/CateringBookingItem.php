<?php

namespace App\Models;

use App\Enums\CateringUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line on a catering sheet: what to cook, how much of it, in what unit.
 *
 * The name, unit and price are copies taken when the line was added, so
 * repricing the catalogue afterwards never rewrites a booking the kitchen has
 * already worked from.
 */
class CateringBookingItem extends Model
{
    protected $fillable = [
        'catering_booking_id', 'catering_item_id', 'name',
        'quantity', 'unit', 'unit_price', 'line_total', 'note',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit' => CateringUnit::class,
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<CateringBooking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(CateringBooking::class, 'catering_booking_id');
    }

    /** Null once the catalogue entry is gone; the copy above still stands. */
    public function cateringItem(): BelongsTo
    {
        return $this->belongsTo(CateringItem::class);
    }

    /** "5 kg", "200 portions". */
    public function amount(): string
    {
        return $this->unit->format((float) $this->quantity);
    }

    public function recalculate(): void
    {
        $this->line_total = $this->unit_price === null
            ? 0
            : round((float) $this->unit_price * (float) $this->quantity, 2);
    }
}
