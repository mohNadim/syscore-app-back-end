<?php

namespace Database\Factories;

use App\Models\GalleryAlbum;
use App\Models\GalleryPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GalleryPhoto>
 */
class GalleryPhotoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'album_id' => GalleryAlbum::factory(),
            'image_url' => fake()->imageUrl(),
            'caption_ar' => fake()->sentence(3),
            'caption_en' => fake()->sentence(3),
            'sort_order' => 0,
        ];
    }
}
