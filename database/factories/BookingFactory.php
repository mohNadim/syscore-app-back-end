<?php

namespace Database\Factories;

use App\Models\BookableUnit;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_number' => fake()->unique()->bothify('BK-########'),
            'user_id' => User::factory(),
            'guest_name' => null,
            'guest_phone' => null,
            'unit_id' => BookableUnit::factory(),
            'sport_id' => null,
            'series_id' => null,
            'starts_at' => $startsAt = fake()->dateTimeBetween('+1 day', '+1 month'),
            'ends_at' => (clone $startsAt)->modify('+60 minutes'),
            'status' => 'confirmed',
            'source' => 'app',
            'payment_mode' => 'cash_full',
            'base_price' => $price = fake()->numberBetween(50, 200) * 1000,
            'discount_id' => null,
            'discount_amount' => 0,
            'final_price' => $price,
            'deposit_amount' => 0,
            'paid_amount' => 0,
            'refund_amount' => 0,
        ];
    }

    public function pendingDeposit(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending_deposit',
            'payment_mode' => 'deposit_then_cash',
            'deposit_due_at' => now()->addHour(),
        ]);
    }

    public function guest(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'guest_name' => fake()->name(),
            'guest_phone' => '+9639'.fake()->numerify('########'),
            'source' => 'front_desk',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }
}
