<?php

namespace Database\Factories;

use App\Models\UserCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserCategory>
 */
class UserCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name_ar' => fake()->word(),
            'name_en' => fake()->word(),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
