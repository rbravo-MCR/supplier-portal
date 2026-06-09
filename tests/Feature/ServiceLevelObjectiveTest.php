<?php

use App\Modules\System\Application\Services\ServiceLevelReportService;

test('service level configuration represents documented sla slo sli and alert thresholds', function () {
    expect(config('service-levels.sla.availability_percentage'))->toBe(99.9)
        ->and(config('service-levels.sla.monthly_downtime_minutes'))->toBe(43)
        ->and(config('service-levels.slo.login.p95_ms'))->toBe(1000)
        ->and(config('service-levels.slo.login.p99_ms'))->toBe(2000)
        ->and(config('service-levels.slo.queries.p95_ms'))->toBe(500)
        ->and(config('service-levels.slo.queries.p99_ms'))->toBe(1000)
        ->and(config('service-levels.slo.dashboard.p95_ms'))->toBe(2000)
        ->and(config('service-levels.slo.excel_export.p95_ms'))->toBe(300000)
        ->and(config('service-levels.alerts.failed_jobs_greater_than'))->toBe(20)
        ->and(config('service-levels.alerts.store_forward_greater_than'))->toBe(100);
});

test('service level catalog exposes documented sli metrics', function () {
    $catalog = app(ServiceLevelReportService::class)->catalog();

    expect($catalog['availability'])->toBe(['uptime_percentage'])
        ->and($catalog['errors'])->toBe(['errors_per_minute', 'errors_by_module'])
        ->and($catalog['database'])->toBe(['slow_queries', 'active_connections', 'locks'])
        ->and($catalog['queue'])->toBe(['pending_jobs', 'failed_jobs', 'average_execution_ms']);
});

test('service level report is healthy when observed metrics meet objectives', function () {
    $report = app(ServiceLevelReportService::class)->evaluate([
        'uptime_percentage' => 99.95,
        'login_p95_ms' => 900,
        'login_p99_ms' => 1900,
        'queries_p95_ms' => 450,
        'queries_p99_ms' => 900,
        'dashboard_p95_ms' => 1500,
        'excel_export_p95_ms' => 240000,
        'failed_jobs' => 0,
        'store_forward_pending' => 0,
    ]);

    expect($report['status'])->toBe('healthy')
        ->and($report['alerts'])->toBeEmpty()
        ->and($report['checks']['availability']['status'])->toBe('healthy')
        ->and($report['checks']['excel_export_p95']['target'])->toBe(300000);
});

test('service level report breaches when slo or alert thresholds are exceeded', function () {
    $report = app(ServiceLevelReportService::class)->evaluate([
        'uptime_percentage' => 99.8,
        'login_p95_ms' => 1200,
        'login_p99_ms' => 2500,
        'queries_p95_ms' => 700,
        'queries_p99_ms' => 1300,
        'dashboard_p95_ms' => 2500,
        'excel_export_p95_ms' => 360000,
        'failed_jobs' => 21,
        'store_forward_pending' => 101,
        'circuit_breaker_open' => true,
        'postgresql_status' => 'down',
        'redis_status' => 'down',
    ]);

    expect($report['status'])->toBe('breached')
        ->and($report['checks']['availability']['status'])->toBe('breached')
        ->and($report['checks']['login_p95']['status'])->toBe('breached')
        ->and(collect($report['alerts'])->pluck('name')->all())->toContain(
            'availability_below_target',
            'failed_jobs_greater_than_threshold',
            'store_forward_greater_than_threshold',
            'circuit_breaker_open',
            'postgresql_down',
            'redis_down',
        );
});

test('service level command exits successfully when metrics meet objectives', function () {
    $this->artisan('system:service-level-check', [
        '--uptime' => '99.95',
        '--login-p95' => '900',
        '--login-p99' => '1900',
        '--queries-p95' => '450',
        '--queries-p99' => '900',
        '--dashboard-p95' => '1500',
        '--excel-export-p95' => '240000',
    ])
        ->expectsOutput('Service level status: healthy')
        ->assertSuccessful();
});

test('service level command fails when alert thresholds are breached', function () {
    $this->artisan('system:service-level-check', [
        '--uptime' => '99.8',
        '--failed-jobs' => '21',
        '--store-forward' => '101',
        '--postgresql-down' => true,
    ])
        ->expectsOutput('Service level status: breached')
        ->assertFailed();
});
