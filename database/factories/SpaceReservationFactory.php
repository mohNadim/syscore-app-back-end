<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Space;
use App\Models\SpaceReservation;
use App\Models\TrainingSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpaceReservation>
 */
class SpaceReservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'space_id' => Space::factory(),
            'starts_at' => $startsAt = fake()->dateTimeBetween('+1 day', '+1 month'),
            'ends_at' => (clone $startsAt)->modify('+60 minutes'),
            'booking_id' => Booking::factory(),
            'session_id' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function forSession(): static
    {
        return $this->state(fn (array $attributes) => [
            'booking_id' => null,
            'session_id' => TrainingSession::factory(),
        ]);
    }
}
