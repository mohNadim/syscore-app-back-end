<?php

namespace Database\Factories;

use App\Models\AcademyGroup;
use App\Models\Child;
use App\Models\WaitlistEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaitlistEntry>
 */
class WaitlistEntryFactory extends Factory
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
            'child_id' => Child::factory(),
            'status' => 'waiting',
            'offered_at' => null,
        ];
    }
}
