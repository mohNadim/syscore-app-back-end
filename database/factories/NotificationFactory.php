<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => fake()->word(),
            'title_ar' => fake()->sentence(4),
            'title_en' => fake()->sentence(4),
            'body_ar' => fake()->sentence(),
            'body_en' => fake()->sentence(),
            'data' => null,
            'announcement_id' => null,
            'push_sent_at' => null,
            'read_at' => null,
        ];
    }
}
