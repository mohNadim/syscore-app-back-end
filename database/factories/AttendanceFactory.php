<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\Child;
use App\Models\Staff;
use App\Models\TrainingSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => TrainingSession::factory(),
            'child_id' => Child::factory(),
            'status' => fake()->randomElement(['present', 'absent']),
            'marked_by_staff_id' => Staff::factory(),
            'marked_at' => now(),
        ];
    }

    public function present(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'present',
        ]);
    }

    public function absent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'absent',
        ]);
    }
}
