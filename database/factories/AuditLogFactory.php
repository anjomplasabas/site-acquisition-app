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
            'user_id' => null,
            'action' => fake()->randomElement(['pipelines_store', 'pipelines_update', 'logout', 'roles_store']),
            'subject_type' => null,
            'subject_id' => null,
            'description' => fake()->sentence(),
            'ip_address' => fake()->ipv4(),
            'metadata' => null,
            'created_at' => fake()->dateTimeBetween('-30 days'),
        ];
    }
}
