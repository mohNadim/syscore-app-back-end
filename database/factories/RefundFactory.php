<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'amount' => fake()->numberBetween(5, 50) * 1000,
            'method' => 'cash',
            'reason' => fake()->sentence(),
            'provider_reference' => null,
            'processed_by_staff_id' => null,
        ];
    }
}
