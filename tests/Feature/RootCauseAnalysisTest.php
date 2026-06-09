<?php

use App\Models\Supplier;
use App\Models\SystemIncident;
use App\Models\User;
use App\Modules\System\Application\DTOs\RecordRootCauseAnalysisData;
use App\Modules\System\Application\UseCases\RecordRootCauseAnalysis;
use Illuminate\Support\Facades\Schema;

test('system incidents table stores incident and rca template fields', function () {
    expect(Schema::hasColumns('system_incidents', [
        'incident_id',
        'correlation_id',
        'supplier_id',
        'user_id',
        'module',
        'action',
        'severity',
        'exception_class',
        'safe_message',
        'technical_message',
        'stack_trace',
        'payload_json',
        'status',
        'started_at',
        'ended_at',
        'responsible',
        'executive_summary',
        'affected_users_count',
        'affected_suppliers',
        'affected_functionality',
        'timeline',
        'technical_root_cause',
        'organizational_root_cause',
        'contributing_factors',
        'immediate_corrective_actions',
        'permanent_corrective_actions',
        'future_prevention',
        'architect_approved_by',
        'technical_lead_approved_by',
        'approved_at',
        'resolved_at',
    ]))->toBeTrue();
});

test('root cause analysis is recorded against an existing incident', function () {
    $incident = SystemIncident::factory()->create([
        'incident_id' => 'INC-RCA123456',
        'module' => 'booking',
        'status' => 'open',
    ]);

    $resolvedIncident = app(RecordRootCauseAnalysis::class)->handle(new RecordRootCauseAnalysisData(
        incidentId: $incident->incident_id,
        startedAt: now()->subMinutes(30),
        endedAt: now(),
        severity: 'critical',
        module: 'booking',
        responsible: 'Operations Lead',
        executiveSummary: 'Booking confirmations were delayed for suppliers during a database outage.',
        affectedUsersCount: 42,
        affectedSuppliers: ['SUP-ONE', 'SUP-TWO'],
        affectedFunctionality: ['Bookings', 'Store & Forward'],
        timeline: [
            ['time' => '08:00', 'event' => 'Evento detectado'],
            ['time' => '08:30', 'event' => 'Recuperación'],
        ],
        technicalRootCause: 'Primary database connection pool exhausted.',
        organizationalRootCause: 'No capacity review after import volume increased.',
        contributingFactors: ['Missing alert for connection saturation'],
        immediateCorrectiveActions: ['Restarted workers'],
        permanentCorrectiveActions: ['Add pool saturation alert'],
        futurePrevention: ['Automated recovery validation'],
        architectApprovedBy: 'Architect',
        technicalLeadApprovedBy: 'Tech Lead',
        approvedAt: now(),
    ));

    expect($resolvedIncident->status)->toBe('resolved')
        ->and($resolvedIncident->resolved_at)->not->toBeNull()
        ->and($resolvedIncident->executive_summary)->toContain('Booking confirmations')
        ->and($resolvedIncident->affected_users_count)->toBe(42)
        ->and($resolvedIncident->affected_suppliers)->toBe(['SUP-ONE', 'SUP-TWO'])
        ->and($resolvedIncident->affected_functionality)->toBe(['Bookings', 'Store & Forward'])
        ->and($resolvedIncident->timeline)->toHaveCount(2)
        ->and($resolvedIncident->technical_root_cause)->toBe('Primary database connection pool exhausted.')
        ->and($resolvedIncident->organizational_root_cause)->toBe('No capacity review after import volume increased.')
        ->and($resolvedIncident->architect_approved_by)->toBe('Architect')
        ->and($resolvedIncident->technical_lead_approved_by)->toBe('Tech Lead')
        ->and($resolvedIncident->approved_at)->not->toBeNull();
});

test('system incident keeps supplier and user context for RCA impact review', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'supplier_id' => $supplier->id,
    ]);

    $incident = SystemIncident::factory()->create([
        'supplier_id' => $supplier->id,
        'user_id' => $user->id,
    ]);

    expect($incident->supplier->is($supplier))->toBeTrue()
        ->and($incident->user->is($user))->toBeTrue();
});
