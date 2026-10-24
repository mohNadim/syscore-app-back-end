<?php

namespace Database\Factories;

use App\Models\OtpCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OtpCode>
 */
class OtpCodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phone' => '+9639'.fake()->numerify('########'),
            'code_hash' => fake()->sha256(),
            'channel' => fake()->randomElement(['sms', 'whatsapp', 'telegram']),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
            'consumed_at' => null,
        ];
    }
}
