<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // The controller authorizes against the order policy.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
