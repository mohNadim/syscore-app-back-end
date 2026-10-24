<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\Space;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Space>
 */
class SpaceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'facility_id' => Facility::factory(),
            'code' => fake()->unique()->bothify('??-##'),
            'name_ar' => fake()->word(),
            'name_en' => fake()->word(),
        ];
    }
}
