<?php

namespace App\Http\Resources;

use App\Models\Order;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The shape the POS order screen consumes. Amounts are sent both raw (for
 * comparisons) and formatted (so the browser never guesses at currency).
 *
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $settings = app(SettingsService::class);

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'status' => $this->status->value,
            'fulfillment_status' => $this->fulfillment_status?->value,
            'payment_status' => $this->payment_status->value,
            'table' => $this->whenLoaded('table', fn () => $this->table?->name),
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'note' => $this->note,
            'item_count' => $this->itemCount(),
            'items' => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'unit_price_formatted' => $settings->formatMoney($item->unit_price),
                'line_total' => (float) $item->line_total,
                'line_total_formatted' => $settings->formatMoney($item->line_total),
                'note' => $item->note,
            ])->values(),
            'subtotal' => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'tax_amount' => (float) $this->tax_amount,
            'total' => (float) $this->total,
            'formatted' => [
                'subtotal' => $settings->formatMoney($this->subtotal),
                'discount_amount' => $settings->formatMoney($this->discount_amount),
                'tax_amount' => $settings->formatMoney($this->tax_amount),
                'total' => $settings->formatMoney($this->total),
            ],
        ];
    }
}
