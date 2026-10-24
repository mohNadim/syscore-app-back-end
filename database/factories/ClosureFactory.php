<?php

namespace Database\Factories;

use App\Models\Closure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Closure>
 */
class ClosureFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->addDays(fake()->numberBetween(1, 30));

        return [
            'facility_id' => null,
            'space_id' => null,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
            'reason_ar' => fake()->sentence(3),
            'reason_en' => fake()->sentence(3),
            'created_by_staff_id' => null,
        ];
    }
}
