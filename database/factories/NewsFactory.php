<?php

namespace Database\Factories;

use App\Models\News;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<News>
 */
class NewsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title_ar' => fake()->sentence(),
            'title_en' => fake()->sentence(),
            'body_ar' => fake()->paragraphs(3, true),
            'body_en' => fake()->paragraphs(3, true),
            'cover_url' => null,
            'is_published' => false,
            'published_at' => null,
            'created_by_staff_id' => null,
        ];
    }
}
