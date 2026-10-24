<?php

namespace Database\Factories;

use App\Models\Coach;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coach>
 */
class CoachFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'full_name_ar' => fake()->name(),
            'full_name_en' => fake()->name(),
            'photo_url' => null,
            'bio_ar' => fake()->paragraph(),
            'bio_en' => fake()->paragraph(),
            'sport_id' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
