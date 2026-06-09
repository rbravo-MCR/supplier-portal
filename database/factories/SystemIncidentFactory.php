<?php

namespace Database\Factories;

use App\Models\SystemIncident;
use App\Shared\Support\IncidentId;
use Illuminate\Database\Eloquent\Factories\Factory;
use RuntimeException;

/**
 * @extends Factory<SystemIncident>
 */
class SystemIncidentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'incident_id' => IncidentId::generate(),
            'correlation_id' => fake()->uuid(),
            'supplier_id' => null,
            'user_id' => null,
            'module' => 'system',
            'action' => 'critical_error',
            'severity' => 'critical',
            'exception_class' => RuntimeException::class,
            'safe_message' => 'Estamos teniendo una intermitencia temporal.',
            'technical_message' => 'Connection refused',
            'stack_trace' => null,
            'payload_json' => [],
            'status' => 'open',
            'started_at' => now()->subMinutes(30),
            'ended_at' => null,
            'responsible' => null,
            'executive_summary' => null,
            'affected_users_count' => null,
            'affected_suppliers' => null,
            'affected_functionality' => null,
            'timeline' => null,
            'technical_root_cause' => null,
            'organizational_root_cause' => null,
            'contributing_factors' => null,
            'immediate_corrective_actions' => null,
            'permanent_corrective_actions' => null,
            'future_prevention' => null,
            'architect_approved_by' => null,
            'technical_lead_approved_by' => null,
            'approved_at' => null,
            'resolved_at' => null,
        ];
    }
}
