<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use App\Support\UploadLimit;
use Illuminate\Foundation\Http\FormRequest;

class CateringCatalogueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permissions::MANAGE_CATERING);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],

            // Artwork, a page at a time. The cap comes from php.ini rather than
            // a number picked here, so the rule can never be looser than what
            // the server will actually take.
            'images' => ['array'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.UploadLimit::kilobytes()],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'images.*.max' => 'Each page must be smaller than '.UploadLimit::label().'.',
            'images.*.mimes' => 'Artwork must be a JPG, PNG or WebP file.',
            'images.*.image' => 'Artwork must be an image file.',
        ];
    }

    /** The fields that belong on the model itself. */
    public function catalogueAttributes(): array
    {
        return collect($this->validated())->except(['images'])->all();
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }
}
