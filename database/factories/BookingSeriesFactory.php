<?php

namespace Database\Factories;

use App\Models\BookableUnit;
use App\Models\BookingSeries;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingSeries>
 */
class BookingSeriesFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'guest_name' => null,
            'guest_phone' => null,
            'unit_id' => BookableUnit::factory(),
            'sport_id' => null,
            'weekday' => fake()->numberBetween(0, 6),
            'start_time' => '18:00:00',
            'duration_minutes' => 60,
            'first_date' => now()->addWeek()->toDateString(),
            'last_date' => now()->addWeeks(9)->toDateString(),
            'status' => 'active',
            'created_by_staff_id' => null,
            'cancelled_at' => null,
        ];
    }
}
