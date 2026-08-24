<?php

namespace App\Http\Requests;

use App\Enums\CateringStatus;
use App\Enums\CateringUnit;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CateringBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permissions::MANAGE_CATERING);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // The day the food has to be ready is the one thing a booking
            // cannot be without — it is what the calendar is built on.
            'event_date' => ['required', 'date'],
            'event_time' => ['nullable', 'date_format:H:i'],

            'customer_name' => ['nullable', 'string', 'max:120'],
            'customer_phone' => ['nullable', 'string', 'max:32'],
            'pax' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'venue' => ['nullable', 'string', 'max:180'],
            'status' => ['required', Rule::enum(CateringStatus::class)],
            'note' => ['nullable', 'string', 'max:2000'],

            'items' => ['array'],
            'items.*.catering_item_id' => ['nullable', 'integer', 'exists:catering_items,id'],
            'items.*.name' => ['required_with:items.*.quantity', 'nullable', 'string', 'max:150'],
            'items.*.quantity' => ['required_with:items.*.name', 'nullable', 'numeric', 'min:0.001', 'max:99999'],
            'items.*.unit' => ['required_with:items.*.name', 'nullable', Rule::enum(CateringUnit::class)],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'items.*.note' => ['nullable', 'string', 'max:150'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'event_date' => 'event date',
            'pax' => 'number of guests',
            'items.*.name' => 'dish',
            'items.*.quantity' => 'quantity',
            'items.*.unit' => 'unit',
        ];
    }

    /**
     * The sheet, with the empty rows the form always carries stripped out.
     *
     * @return list<array<string, mixed>>
     */
    public function lines(): array
    {
        return collect($this->validated('items') ?? [])
            ->filter(fn (array $line) => filled($line['name'] ?? null) && filled($line['quantity'] ?? null))
            ->values()
            ->all();
    }
}
