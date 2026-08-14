<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Quantity and note are edited independently: the order screen sends whichever
 * one the cashier just touched.
 */
class UpdateOrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // The controller authorizes against the order policy.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'quantity' => ['sometimes', 'integer', 'min:0', 'max:999'],
            'note' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
