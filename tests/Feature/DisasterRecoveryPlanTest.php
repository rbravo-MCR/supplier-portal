<?php

use App\Modules\System\Application\Services\DisasterRecoveryReadinessService;
use App\Modules\System\Application\Services\HealthCheckService;

test('disaster recovery configuration represents the documented rto rpo and backup policies', function () {
    expect(config('disaster-recovery.objectives.rto_minutes'))->toBe(30)
        ->and(config('disaster-recovery.objectives.rpo_minutes'))->toBe(5)
        ->and(config('disaster-recovery.backups.postgresql.full_backup_frequency'))->toBe('daily')
        ->and(config('disaster-recovery.backups.postgresql.incremental_backup_frequency'))->toBe('hourly')
        ->and(config('disaster-recovery.backups.postgresql.retention_days'))->toBe(30)
        ->and(config('disaster-recovery.backups.files.included'))->toBe(['storage', 'excel', 'reports'])
        ->and(config('disaster-recovery.backups.files.retention_days'))->toBe(90);
});

test('disaster recovery readiness is healthy when objectives backups and health checks pass', function () {
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

    $report = app(DisasterRecoveryReadinessService::class)->report();

    expect($report['status'])->toBe('healthy')
        ->and($report['procedures'])->toHaveKeys([
            'postgresql_corrupt',
            'redis_lost',
            'server_down',
            'sqlite_store_forward_saturated',
        ]);
});

test('disaster recovery readiness degrades when objectives or backup policies do not meet the plan', function () {
    config([
        'disaster-recovery.objectives.rto_minutes' => 45,
        'disaster-recovery.objectives.rpo_minutes' => 10,
        'disaster-recovery.backups.postgresql.retention_days' => 7,
        'disaster-recovery.backups.files.included' => ['storage'],
    ]);

    $this->mock(HealthCheckService::class, function ($mock): void {
        $mock->shouldReceive('all')
            ->once()
            ->andReturn([
                'status' => 'healthy',
                'checks' => [],
            ]);
    });

    $report = app(DisasterRecoveryReadinessService::class)->report();

    expect($report['status'])->toBe('degraded')
        ->and($report['checks']['rto']['status'])->toBe('degraded')
        ->and($report['checks']['rpo']['status'])->toBe('degraded')
        ->and($report['checks']['postgresql_backups']['status'])->toBe('degraded')
        ->and($report['checks']['file_backups']['missing'])->toBe(['excel', 'reports']);
});

test('disaster recovery readiness follows recovery validation health status', function () {
    $this->mock(HealthCheckService::class, function ($mock): void {
        $mock->shouldReceive('all')
            ->once()
            ->andReturn([
                'status' => 'down',
                'checks' => [
                    'db' => ['status' => 'down'],
                ],
            ]);
    });

    $report = app(DisasterRecoveryReadinessService::class)->report();

    expect($report['status'])->toBe('down')
        ->and($report['checks']['recovery_validation']['health_status'])->toBe('down');
});

test('disaster recovery command exits successfully only when the plan is ready', function () {
    $this->mock(DisasterRecoveryReadinessService::class, function ($mock): void {
        $mock->shouldReceive('report')
            ->once()
            ->andReturn([
                'status' => 'healthy',
                'checks' => [
                    'rto' => ['status' => 'healthy', 'minutes' => 30, 'maximum_minutes' => 30],
                ],
                'procedures' => [],
            ]);
    });

    $this->artisan('system:disaster-recovery-check')
        ->expectsOutput('Disaster recovery status: healthy')
        ->assertSuccessful();
});

test('disaster recovery command fails when the plan is not ready', function () {
    $this->mock(DisasterRecoveryReadinessService::class, function ($mock): void {
        $mock->shouldReceive('report')
            ->once()
            ->andReturn([
                'status' => 'degraded',
                'checks' => [
                    'rto' => ['status' => 'degraded', 'minutes' => 45, 'maximum_minutes' => 30],
                ],
                'procedures' => [],
            ]);
    });

    $this->artisan('system:disaster-recovery-check')
        ->expectsOutput('Disaster recovery status: degraded')
        ->assertFailed();
});
