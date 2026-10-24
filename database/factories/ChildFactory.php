<?php

namespace Database\Factories;

use App\Models\Child;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Child>
 */
class ChildFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'full_name' => fake()->name(),
            'birth_date' => fake()->dateTimeBetween('-12 years', '-4 years'),
            'gender' => fake()->randomElement(['male', 'female']),
            'health_notes' => null,
            'is_active' => true,
        ];
    }
}
