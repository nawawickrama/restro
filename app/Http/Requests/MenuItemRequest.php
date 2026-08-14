<?php

namespace App\Http\Requests;

use App\Services\MenuItemImageService;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class MenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permissions::MANAGE_MENU);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],

            // Optional food photo. The size cap comes from php.ini rather than
            // a number picked here, so the rule can never be looser than what
            // the server will actually accept.
            'image' => [
                'nullable', 'image', 'mimes:jpg,jpeg,png,webp',
                'max:'.MenuItemImageService::maxUploadKilobytes(),
            ],
            'remove_image' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'image.max' => 'The photo must be smaller than '.MenuItemImageService::maxUploadLabel().'.',
            'image.mimes' => 'The photo must be a JPG, PNG or WebP file.',
        ];
    }

    /** The validated attributes that belong on the model itself. */
    public function menuItemAttributes(): array
    {
        return collect($this->validated())->except(['image', 'remove_image'])->all();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'remove_image' => $this->boolean('remove_image'),
        ]);
    }
}
