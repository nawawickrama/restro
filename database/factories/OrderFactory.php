<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds orders directly for tests that need a starting state. Anything that
 * exercises real behaviour should go through {@see OrderService}
 * instead, so the business rules run.
 *
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'order_number' => 'ORD-'.now()->format('Ymd').'-'.fake()->unique()->numerify('####'),
            'type' => OrderType::Takeaway,
            'status' => OrderStatus::Open,
            'payment_status' => PaymentStatus::Unpaid,
            'user_id' => User::factory(),
            'subtotal' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total' => 0,
        ];
    }

    public function dineIn(?RestaurantTable $table = null): static
    {
        return $this->state(fn () => [
            'type' => OrderType::DineIn,
            'table_id' => ($table ?? RestaurantTable::factory()->create())->id,
        ]);
    }

    public function phone(): static
    {
        return $this->state([
            'type' => OrderType::PhoneTakeaway,
            'customer_name' => fake()->name(),
            'customer_phone' => fake()->numerify('07########'),
        ]);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => OrderStatus::Completed,
            'payment_status' => PaymentStatus::Paid,
            'completed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status' => OrderStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
