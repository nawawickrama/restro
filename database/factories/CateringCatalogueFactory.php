<?php

namespace Database\Factories;

use App\Models\CateringCatalogue;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CateringCatalogue> */
class CateringCatalogueFactory extends Factory
{
    protected $model = CateringCatalogue::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = ucwords(fake()->unique()->words(2, true));

        return [
            'name' => $name,
            'slug' => CateringCatalogue::makeSlug($name),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function hidden(): static
    {
        return $this->state(['is_active' => false]);
    }
}
