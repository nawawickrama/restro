<?php

namespace App\Models;

use App\Enums\CateringStatus;
use Database\Factories\CateringBookingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A job the kitchen has been booked for: who it is for, how many they are
 * feeding, what is being cooked, and the day it has to be ready.
 */
class CateringBooking extends Model
{
    /** @use HasFactory<CateringBookingFactory> */
    use HasFactory;

    protected $fillable = [
        'reference', 'customer_id', 'user_id', 'customer_name', 'customer_phone',
        'pax', 'event_date', 'event_time', 'venue', 'status', 'note', 'total',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'pax' => 'integer',
            'status' => CateringStatus::class,
            'total' => 'decimal:2',
        ];
    }

    /** @return HasMany<CateringBookingItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CateringBookingItem::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** What to call this booking in a list or on the calendar. */
    public function customerLabel(): string
    {
        return $this->customer_name ?: ($this->customer_phone ?: 'No name');
    }

    /** "7.30 PM", or nothing when only the day was agreed. */
    public function timeLabel(): ?string
    {
        return $this->event_time
            ? Carbon::parse($this->event_time)->format('g:i A')
            : null;
    }

    public function isEditable(): bool
    {
        return $this->status !== CateringStatus::Cancelled;
    }

    /** @param Builder<$this> $query */
    public function scopeLive(Builder $query): void
    {
        $query->where('status', '!=', CateringStatus::Cancelled);
    }

    /**
     * @param  Builder<$this>  $query
     *
     * A range rather than whereMonth(): wrapping the column in a function
     * stops the index on event_date being used.
     */
    public function scopeBetween(Builder $query, Carbon $from, Carbon $to): void
    {
        $query->whereBetween('event_date', [$from->toDateString(), $to->toDateString()]);
    }
}
