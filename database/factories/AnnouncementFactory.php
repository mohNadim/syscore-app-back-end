<?php

namespace Database\Factories;

use App\Models\Announcement;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'audience' => 'all',
            'academy_id' => null,
            'sent_by_staff_id' => Staff::factory(),
            'title_ar' => fake()->sentence(),
            'title_en' => fake()->sentence(),
            'body_ar' => fake()->paragraph(),
            'body_en' => fake()->paragraph(),
            'sent_at' => null,
        ];
    }
}
