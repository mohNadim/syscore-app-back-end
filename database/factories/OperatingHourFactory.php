<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\OperatingHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperatingHour>
 */
class OperatingHourFactory extends Factory
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
            'weekday' => fake()->numberBetween(0, 6),
            'open_time' => '08:00:00',
            'close_time' => '22:00:00',
        ];
    }
}
