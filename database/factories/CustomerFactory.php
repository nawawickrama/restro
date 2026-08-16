<?php

namespace Database\Factories;

use App\Enums\CustomerSource;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $phone = '07'.fake()->unique()->numerify('#########');

        return [
            'name' => fake()->name(),
            'phone' => $phone,
            'phone_digits' => $phone,
            'source' => CustomerSource::Manual,
        ];
    }

    public function source(CustomerSource $source): static
    {
        return $this->state(['source' => $source]);
    }

    /** Plenty of customers give a number and never a name. */
    public function nameless(): static
    {
        return $this->state(['name' => null]);
    }
}
