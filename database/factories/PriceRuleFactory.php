<?php

namespace Database\Factories;

use App\Models\BookableUnit;
use App\Models\PriceRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceRule>
 */
class PriceRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => BookableUnit::factory(),
            'weekday' => fake()->numberBetween(0, 6),
            'start_time' => '16:00:00',
            'end_time' => '23:00:00',
            'is_peak' => false,
            'price_per_hour' => fake()->numberBetween(50, 200) * 1000,
            'valid_from' => now()->startOfYear()->toDateString(),
            'valid_to' => null,
        ];
    }

    public function peak(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_peak' => true,
        ]);
    }
}
