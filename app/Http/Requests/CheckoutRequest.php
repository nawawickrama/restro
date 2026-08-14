<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // The controller authorizes against the order policy.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::enum(PaymentMethod::class)],

            // Cash-like methods need the amount handed over; the service checks
            // it actually covers the total.
            'tendered' => [
                'nullable',
                'numeric',
                'min:0',
                Rule::requiredIf(fn () => PaymentMethod::tryFrom((string) $this->input('method'))?->requiresTendered()),
            ],
            'reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['tendered' => 'amount received'];
    }

    public function paymentMethod(): PaymentMethod
    {
        return PaymentMethod::from($this->validated('method'));
    }
}
