<?php

namespace Database\Factories;

use App\Models\Discount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Discount>
 */
class DiscountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name_ar' => fake()->words(3, true),
            'name_en' => fake()->words(3, true),
            'type' => 'percent',
            'value' => fake()->numberBetween(5, 30),
            'target' => 'all',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'time_from' => null,
            'time_to' => null,
            'weekdays' => null,
            'is_active' => true,
            'notify_users' => false,
            'created_by_staff_id' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function fixed(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'fixed',
            'value' => 10000,
        ]);
    }
}
