<?php

namespace Database\Factories;

use App\Models\AcademyGroup;
use App\Models\BookableUnit;
use App\Models\GroupSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupSchedule>
 */
class GroupScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => AcademyGroup::factory(),
            'unit_id' => BookableUnit::factory(),
            'weekday' => fake()->numberBetween(0, 6),
            'start_time' => '16:00:00',
            'end_time' => '17:30:00',
            'valid_from' => now()->startOfYear()->toDateString(),
            'valid_to' => null,
        ];
    }
}
