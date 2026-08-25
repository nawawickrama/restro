<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use App\Support\UploadLimit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

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

            // One row per artwork: the image, and the name it goes out under.
            // The size cap comes from php.ini rather than a number picked here,
            // so the rule can never be looser than what the server will take.
            'artworks' => ['array'],
            'artworks.*.image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.UploadLimit::kilobytes()],
            'artworks.*.title' => ['nullable', 'string', 'max:120'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'artworks.*.image.max' => 'Each artwork must be smaller than '.UploadLimit::label().'.',
            'artworks.*.image.mimes' => 'Artwork must be a JPG, PNG or WebP file.',
            'artworks.*.image.image' => 'Artwork must be an image file.',
        ];
    }

    /** The fields that belong on the model itself. */
    public function catalogueAttributes(): array
    {
        return collect($this->validated())->except(['artworks'])->all();
    }

    /**
     * The artworks that actually carry a file.
     *
     * The form always offers an empty row to fill in, so rows nobody used are
     * dropped rather than saved as blanks.
     *
     * @return list<array{file: UploadedFile, title: string|null}>
     */
    public function artworks(): array
    {
        return collect($this->file('artworks') ?? [])
            ->map(fn ($row, $index) => [
                'file' => $row['image'] ?? null,
                'title' => $this->input("artworks.{$index}.title"),
            ])
            ->filter(fn (array $row) => $row['file'] instanceof UploadedFile)
            ->values()
            ->all();
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }
}
