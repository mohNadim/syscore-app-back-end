<?php

namespace Database\Factories;

use App\Models\Academy;
use App\Models\AcademyGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademyGroup>
 */
class AcademyGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'academy_id' => Academy::factory(),
            'coach_id' => null,
            'name_ar' => fake()->words(2, true),
            'name_en' => fake()->words(2, true),
            'age_min' => 6,
            'age_max' => 12,
            'gender' => fake()->randomElement(['male', 'female', 'mixed']),
            'capacity' => 20,
            'level' => fake()->randomElement(['beginner', 'intermediate', 'advanced']),
            'monthly_price' => fake()->numberBetween(50, 300) * 1000,
            'subscription_days' => 30,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
