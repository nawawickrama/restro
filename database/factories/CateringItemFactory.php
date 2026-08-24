<?php

namespace Database\Factories;

use App\Enums\CateringUnit;
use App\Models\CateringItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CateringItem> */
class CateringItemFactory extends Factory
{
    protected $model = CateringItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => ucwords(fake()->unique()->words(2, true)),
            'default_unit' => CateringUnit::Kilogram,
            'price' => fake()->randomFloat(2, 500, 5000),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
