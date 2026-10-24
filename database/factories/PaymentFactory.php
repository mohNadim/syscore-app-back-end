<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'receipt_number' => fake()->unique()->bothify('RCPT-########'),
            'user_id' => User::factory(),
            'payer_name' => null,
            'payer_phone' => null,
            'amount' => fake()->numberBetween(10, 100) * 1000,
            'method' => 'cash',
            'purpose' => 'booking_full',
            'status' => 'succeeded',
            'booking_id' => Booking::factory(),
            'subscription_id' => null,
            'provider_reference' => null,
            'provider_payload' => null,
            'recorded_by_staff_id' => null,
            'paid_at' => now(),
        ];
    }

    public function forSubscription(): static
    {
        return $this->state(fn (array $attributes) => [
            'purpose' => 'subscription',
            'booking_id' => null,
            'subscription_id' => Subscription::factory(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'paid_at' => null,
        ]);
    }
}
