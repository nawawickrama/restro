<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** One page of a catering catalogue's artwork. */
class CateringCatalogueImage extends Model
{
    protected $fillable = ['catering_catalogue_id', 'path', 'caption', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    /** @return BelongsTo<CateringCatalogue, $this> */
    public function catalogue(): BelongsTo
    {
        return $this->belongsTo(CateringCatalogue::class, 'catering_catalogue_id');
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
