<?php

namespace Database\Factories;

use App\Models\BookableUnit;
use App\Models\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookableUnit>
 */
class BookableUnitFactory extends Factory
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
            'code' => strtoupper(fake()->unique()->bothify('U-###??')),
            'name_ar' => fake()->words(2, true),
            'name_en' => fake()->words(2, true),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
