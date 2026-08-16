<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Customer details on any order.
 *
 * Every order type can carry a name and a number — a dine-in table may leave a
 * number for a callback, a walk-in may want their name read out when the food
 * is up. Nothing here is required, with one exception: a phone order has to be
 * reachable, so its mobile number is mandatory (business rule 13).
 */
class OrderCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // The controller authorizes against the order policy.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'customer_phone' => [
                $this->order()?->type->requiresCustomer() ? 'required' : 'nullable',
                'string',
                'max:32',
            ],
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

    private function order(): ?Order
    {
        $order = $this->route('order');

        return $order instanceof Order ? $order : null;
    }
}
