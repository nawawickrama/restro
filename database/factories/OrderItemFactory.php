<?php

namespace Database\Factories;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderItem> */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $price = fake()->randomFloat(2, 100, 2000);
        $quantity = fake()->numberBetween(1, 3);

        return [
            'order_id' => Order::factory(),
            'menu_item_id' => MenuItem::factory(),
            'name' => fake()->words(2, true),
            'unit_price' => $price,
            'quantity' => $quantity,
            'line_total' => round($price * $quantity, 2),
        ];
    }
}
