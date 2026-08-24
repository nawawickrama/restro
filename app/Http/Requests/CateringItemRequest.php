<?php

namespace App\Http\Requests;

use App\Enums\CateringUnit;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CateringItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permissions::MANAGE_CATERING);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('catering_items', 'name')->ignore($this->route('catering_item')),
            ],
            'default_unit' => ['required', Rule::enum(CateringUnit::class)],

            // A kitchen often books the job first and prices it later.
            'price' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }
}
