<?php

use App\Models\OutboxEvent;
use App\Models\Supplier;
use App\Models\User;
use App\Modules\System\Application\Services\HealthCheckService;
use App\Shared\Support\StructuredLogContext;
use Illuminate\Support\Facades\DB;

test('health endpoint returns aggregate status', function () {
    $this->getJson('/health')
        ->assertOk()
        ->assertJsonStructure([
            'status',
            'checks' => [
                'db' => ['status'],
                'redis' => ['status'],
                'queue' => ['status'],
                'storage' => ['status'],
                'outbox' => ['status', 'pending_operations', 'threshold'],
                'failed_jobs' => ['status', 'failed'],
            ],
        ]);
});

test('individual health endpoints return component status', function (string $endpoint) {
    $this->getJson($endpoint)
        ->assertOk()
        ->assertJsonStructure(['status']);
})->with([
    '/health/db',
    '/health/redis',
    '/health/queue',
    '/health/storage',
    '/health/outbox',
    '/health/failed-jobs',
]);

test('store and forward health degrades when pending operations exceed the runbook threshold', function () {
    OutboxEvent::factory()
        ->count(101)
        ->create();

    $this->getJson('/health/outbox')
        ->assertOk()
        ->assertJson([
            'status' => 'degraded',
            'pending_operations' => 101,
            'threshold' => 100,
        ]);
});

test('failed jobs health degrades when the recovery validation is not clean', function () {
    DB::table('failed_jobs')->insert([
        'uuid' => fake()->uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => '{}',
        'exception' => 'RuntimeException',
        'failed_at' => now(),
    ]);

    $this->getJson('/health/failed-jobs')
        ->assertOk()
        ->assertJson([
            'status' => 'degraded',
            'failed' => 1,
        ]);
});

test('runbook health command exits successfully only when recovery validation is healthy', function () {
    $this->mock(HealthCheckService::class, function ($mock): void {
        $mock->shouldReceive('all')
            ->once()
            ->andReturn([
                'status' => 'healthy',
                'checks' => [
                    'db' => ['status' => 'healthy'],
                    'redis' => ['status' => 'healthy'],
                    'queue' => ['status' => 'healthy'],
                    'storage' => ['status' => 'healthy'],
                    'outbox' => ['status' => 'healthy', 'pending_operations' => 0, 'threshold' => 100],
                    'failed_jobs' => ['status' => 'healthy', 'failed' => 0],
                ],
            ]);
    });

    $this->artisan('system:health-check')
        ->expectsOutput('System status: healthy')
        ->assertSuccessful();
});

test('runbook health command fails when a recovery validation check is degraded', function () {
    $this->mock(HealthCheckService::class, function ($mock): void {
        $mock->shouldReceive('all')
            ->once()
            ->andReturn([
                'status' => 'degraded',
                'checks' => [
                    'db' => ['status' => 'healthy'],
                    'redis' => ['status' => 'healthy'],
                    'queue' => ['status' => 'healthy'],
                    'storage' => ['status' => 'healthy'],
                    'outbox' => ['status' => 'degraded', 'pending_operations' => 101, 'threshold' => 100],
                    'failed_jobs' => ['status' => 'healthy', 'failed' => 0],
                ],
            ]);
    });

    $this->artisan('system:health-check')
        ->expectsOutput('System status: degraded')
        ->assertFailed();
});

test('structured log context includes required observability fields', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'supplier_id' => $supplier->id,
    ]);

    $context = StructuredLogContext::for(
        module: 'booking',
        action: 'confirmed',
        incidentId: 'INC-TEST',
        user: $user,
        correlationId: 'corr-123',
        requestId: 'req-456',
    );

    expect($context)->toHaveKeys([
        'incident_id',
        'correlation_id',
        'request_id',
        'supplier_id',
        'user_id',
        'module',
        'action',
    ])
        ->and($context['supplier_id'])->toBe($supplier->id)
        ->and($context['user_id'])->toBe($user->id)
        ->and($context['module'])->toBe('booking')
        ->and($context['action'])->toBe('confirmed');
});
