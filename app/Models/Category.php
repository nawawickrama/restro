<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected $fillable = ['name', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return HasMany<MenuItem, $this> */
    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    /** Items a cashier is allowed to sell right now. */
    public function activeMenuItems(): HasMany
    {
        return $this->menuItems()->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    /**
     * The menu as it stands: every active category with something on sale in
     * it, in menu order, with those items loaded. The order screen, the POS
     * menu and the public page all show exactly this.
     *
     * @return Collection<int, self>
     */
    public static function onTheMenu(): Collection
    {
        return static::query()
            ->active()
            ->ordered()
            ->with('activeMenuItems')
            ->get()
            // A category with nothing sellable in it is not worth a heading.
            ->filter(fn (self $category) => $category->activeMenuItems->isNotEmpty())
            ->values();
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
