<?php

namespace Database\Factories;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
            'uuid' => (string) Str::uuid(),
            'supplier_id' => null,
            'user_id' => null,
            'module' => 'supplier',
            'action' => 'created',
            'entity_type' => 'supplier',
            'entity_id' => null,
            'old_values' => null,
            'new_values' => [],
            'ip_address' => null,
            'user_agent' => null,
        ];
    }
}
