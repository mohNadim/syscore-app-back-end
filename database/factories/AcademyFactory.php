<?php

namespace Database\Factories;

use App\Models\Academy;
use App\Models\Sport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Academy>
 */
class AcademyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sport_id' => Sport::factory(),
            'name_ar' => fake()->words(2, true),
            'name_en' => fake()->words(2, true),
            'description_ar' => fake()->sentence(),
            'description_en' => fake()->sentence(),
            'cover_url' => null,
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
