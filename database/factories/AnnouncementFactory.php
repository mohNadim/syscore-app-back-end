<?php

namespace Database\Factories;

use App\Models\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Note: sent_by_staff_id is a required column that references staff. It is not set here
     * until a Staff model and factory exist, so callers must pass it explicitly.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'audience' => 'all',
            'academy_id' => null,
            'title_ar' => fake()->sentence(),
            'title_en' => fake()->sentence(),
            'body_ar' => fake()->paragraph(),
            'body_en' => fake()->paragraph(),
            'sent_at' => null,
        ];
    }
}
