<?php

namespace Database\Factories;

use App\Models\AcademyGroup;
use App\Models\Child;
use App\Models\Enrollment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'child_id' => Child::factory(),
            'group_id' => AcademyGroup::factory(),
            'status' => 'active',
            'enrolled_at' => now(),
            'ended_at' => null,
            'created_by_staff_id' => null,
        ];
    }

    public function ended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'ended',
            'ended_at' => now(),
        ]);
    }
}
