<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Contact details for a phone order, captured after the food has been taken
 * down — which is the order a real call happens in.
 */
class StorePhoneOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // The controller authorizes against the order policy.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // The number is how the counter finds the customer when they turn
            // up, so it is the one detail that is not optional.
            'customer_phone' => ['required', 'string', 'max:32'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'customer_phone' => 'mobile number',
            'customer_name' => 'customer name',
        ];
    }
}
