<?php

namespace App\Models;

use Database\Factories\CateringCatalogueFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A category of the catering brochure — "Wedding packages", "Office lunches" —
 * holding the pages of artwork that describe it.
 */
class CateringCatalogue extends Model
{
    /** @use HasFactory<CateringCatalogueFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** Public links are by slug, so a shared address survives a rename. */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return HasMany<CateringCatalogueImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(CateringCatalogueImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /** The first page, used as the cover on the public index. */
    public function cover(): ?CateringCatalogueImage
    {
        return $this->images->first();
    }

    /**
     * A slug that is unique and stays put.
     *
     * Generated once when the category is created; renaming afterwards leaves
     * the address alone, because it may already be printed on something.
     */
    public static function makeSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'catalogue';
        $slug = $base;
        $suffix = 2;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /** @param Builder<$this> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param Builder<$this> $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }
}
