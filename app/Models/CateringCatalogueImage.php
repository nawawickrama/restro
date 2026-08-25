<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One artwork in a catalogue category — a package in its own right, such as
 * "Package 1" under Weddings, rather than a page of one long brochure.
 */
class CateringCatalogueImage extends Model
{
    protected $fillable = ['catering_catalogue_id', 'path', 'title', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    /** @return BelongsTo<CateringCatalogue, $this> */
    public function catalogue(): BelongsTo
    {
        return $this->belongsTo(CateringCatalogue::class, 'catering_catalogue_id');
    }

    /** What to call this package when it has not been named. */
    public function displayTitle(int $position): string
    {
        return filled($this->title) ? $this->title : "Package {$position}";
    }

    /**
     * Host-relative on purpose: the same page is opened from a phone on the
     * restaurant's wifi, a laptop and a shared link, and a URL built from
     * APP_URL points all but one of them at the wrong machine.
     */
    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
