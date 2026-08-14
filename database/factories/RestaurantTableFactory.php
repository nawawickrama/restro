<?php

namespace Database\Factories;

use App\Models\RestaurantTable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RestaurantTable> */
class RestaurantTableFactory extends Factory
{
    protected $model = RestaurantTable::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => 'Table '.fake()->unique()->numberBetween(1, 9999),
            'seats' => 4,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
