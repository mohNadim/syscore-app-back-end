<?php

namespace Database\Factories;

use App\Models\AcademyGroup;
use App\Models\BookableUnit;
use App\Models\TrainingSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingSession>
 */
class TrainingSessionFactory extends Factory
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
            'schedule_id' => null,
            'unit_id' => BookableUnit::factory(),
            'coach_id' => null,
            'starts_at' => $startsAt = fake()->dateTimeBetween('+1 day', '+1 month'),
            'ends_at' => (clone $startsAt)->modify('+90 minutes'),
            'status' => 'scheduled',
            'original_starts_at' => null,
            'change_reason' => null,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }
}
