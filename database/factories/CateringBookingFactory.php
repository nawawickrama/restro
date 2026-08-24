<?php

namespace Database\Factories;

use App\Enums\CateringStatus;
use App\Models\CateringBooking;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CateringBooking> */
class CateringBookingFactory extends Factory
{
    protected $model = CateringBooking::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'reference' => 'CAT-'.now()->format('Ymd').'-'.fake()->unique()->numerify('####'),
            'user_id' => User::factory(),
            'customer_name' => fake()->name(),
            'customer_phone' => '07'.fake()->numerify('########'),
            'pax' => fake()->numberBetween(20, 400),
            'event_date' => today()->addDays(fake()->numberBetween(1, 60)),
            'status' => CateringStatus::Enquiry,
            'total' => 0,
        ];
    }

    public function on(string $date): static
    {
        return $this->state(['event_date' => $date]);
    }

    public function status(CateringStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
