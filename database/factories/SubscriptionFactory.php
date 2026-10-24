<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'status' => 'pending_payment',
            'period_start' => null,
            'period_end' => null,
            'base_price' => $price = fake()->numberBetween(50, 300) * 1000,
            'discount_id' => null,
            'discount_amount' => 0,
            'final_price' => $price,
            'renewed_from_id' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'period_start' => now(),
            'period_end' => now()->addDays(30),
        ]);
    }
}
