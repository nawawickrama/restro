<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'user_id' => User::factory(),
            'method' => PaymentMethod::Cash,
            'amount' => fake()->randomFloat(2, 100, 5000),
            'change_amount' => 0,
            'paid_at' => now(),
        ];
    }

    public function card(): static
    {
        return $this->state(['method' => PaymentMethod::Card, 'tendered' => null]);
    }
}
