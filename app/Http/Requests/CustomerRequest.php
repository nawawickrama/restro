<?php

namespace App\Http\Requests;

use App\Models\Customer;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permissions::MANAGE_CUSTOMERS);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // The number is the identity — a name alone cannot tell two people
            // apart — so it is the one required field. Uniqueness is checked
            // against the digits, so the same number typed with spaces is
            // still caught as a duplicate.
            'phone' => ['required', 'string', 'max:32'],
            'phone_digits' => [
                Rule::unique('customers', 'phone_digits')->ignore($this->route('customer')),
            ],
            'name' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['phone_digits' => 'mobile number'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['phone_digits.unique' => 'A customer with this mobile number already exists.'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['phone_digits' => Customer::normalisePhone($this->input('phone'))]);
    }
}
