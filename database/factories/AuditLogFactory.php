<?php

namespace Database\Factories;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_type' => 'system',
            'actor_user_id' => null,
            'actor_staff_id' => null,
            'action' => 'updated',
            'entity_type' => 'news',
            'entity_id' => fake()->numberBetween(1, 1000),
            'before' => null,
            'after' => null,
            'ip_address' => fake()->ipv4(),
        ];
    }
}
