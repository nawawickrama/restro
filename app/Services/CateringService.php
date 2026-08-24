<?php

namespace App\Services;

use App\Enums\CateringStatus;
use App\Enums\CateringUnit;
use App\Enums\CustomerSource;
use App\Models\CateringBooking;
use App\Models\CateringItem;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Everything that changes a catering booking.
 *
 * A booking is written in one go from the form rather than built up line by
 * line the way an order is at the till, so saving replaces the whole sheet
 * inside a transaction: the kitchen never sees half a booking.
 */
class CateringService
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $lines
     */
    public function create(array $data, array $lines, User $user): CateringBooking
    {
        return DB::transaction(function () use ($data, $lines, $user): CateringBooking {
            $booking = CateringBooking::query()->create([
                ...$this->attributes($data),
                'reference' => $this->reference(),
                'user_id' => $user->id,
                'status' => $data['status'] ?? CateringStatus::Enquiry->value,
            ]);

            $this->replaceItems($booking, $lines);

            return $booking;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $lines
     */
    public function update(CateringBooking $booking, array $data, array $lines): CateringBooking
    {
        return DB::transaction(function () use ($booking, $data, $lines): CateringBooking {
            $booking->update($this->attributes($data) + ['status' => $data['status'] ?? $booking->status->value]);

            $this->replaceItems($booking, $lines);

            return $booking;
        });
    }

    /**
     * The fields that come straight off the form.
     *
     * A booking taken with a phone number is filed against the customer record
     * behind it, so the catering diary and the till agree about who somebody
     * is. The typed name and number stay on the booking regardless.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'customer_name' => $this->cleaned($data['customer_name'] ?? null),
            'customer_phone' => $this->cleaned($data['customer_phone'] ?? null),
            'customer_id' => $this->customerFor($data)?->id,
            'pax' => $data['pax'] ?? null,
            'event_date' => $data['event_date'],
            'event_time' => $this->cleaned($data['event_time'] ?? null),
            'venue' => $this->cleaned($data['venue'] ?? null),
            'note' => $this->cleaned($data['note'] ?? null),
        ];
    }

    /** @param array<string, mixed> $data */
    private function customerFor(array $data): ?Customer
    {
        $phone = $this->cleaned($data['customer_phone'] ?? null);

        if (blank($phone)) {
            return null;
        }

        $digits = Customer::normalisePhone($phone);

        if ($digits === '') {
            return null;
        }

        $existing = Customer::query()->where('phone_digits', $digits)->lockForUpdate()->first();

        if ($existing) {
            if (blank($existing->name) && filled($data['customer_name'] ?? null)) {
                $existing->update(['name' => $data['customer_name']]);
            }

            return $existing;
        }

        return Customer::query()->create([
            'name' => $this->cleaned($data['customer_name'] ?? null),
            'phone' => $phone,
            'phone_digits' => $digits,
            // Catering is booked over the phone or across the counter; either
            // way somebody rang or walked in about it.
            'source' => CustomerSource::Phone,
        ]);
    }

    /**
     * Replace the sheet wholesale.
     *
     * Simpler than reconciling line by line, and correct: the form submits the
     * sheet as it should now read, so anything absent from it was removed.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    private function replaceItems(CateringBooking $booking, array $lines): void
    {
        $booking->items()->delete();

        $total = 0.0;

        foreach ($lines as $position => $line) {
            if (blank($line['name'] ?? null) || ! isset($line['quantity'])) {
                continue;
            }

            $catalogue = isset($line['catering_item_id'])
                ? CateringItem::query()->find($line['catering_item_id'])
                : null;

            $price = $line['unit_price'] ?? null;
            $quantity = round((float) $line['quantity'], 3);
            $lineTotal = $price === null ? 0 : round((float) $price * $quantity, 2);

            $booking->items()->create([
                'catering_item_id' => $catalogue?->id,
                'name' => $line['name'],
                'quantity' => $quantity,
                'unit' => $line['unit'] ?? $catalogue?->default_unit?->value ?? CateringUnit::Portion->value,
                'unit_price' => $price,
                'line_total' => $lineTotal,
                'note' => $line['note'] ?? null,
            ]);

            $total += $lineTotal;
        }

        $booking->forceFill(['total' => round($total, 2)])->save();
        $booking->load('items');
    }

    public function setStatus(CateringBooking $booking, CateringStatus $status): void
    {
        $booking->forceFill(['status' => $status])->save();
    }

    /**
     * Human-readable, sequential within a day: CAT-20260824-0001.
     *
     * The unique index on the column is the real guard; a clash simply takes
     * the next number.
     */
    private function reference(): string
    {
        $prefix = 'CAT-'.now()->format('Ymd').'-';

        $last = CateringBooking::query()
            ->where('reference', 'like', $prefix.'%')
            ->orderByDesc('reference')
            ->value('reference');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function cleaned(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
