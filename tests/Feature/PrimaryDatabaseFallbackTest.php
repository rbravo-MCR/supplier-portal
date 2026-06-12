<?php

use App\Modules\System\Application\Services\LocalIncidentStore;
use App\Modules\System\Application\Services\PrimaryDatabaseCircuitBreaker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

test('local incident store persists incidents in sqlite fallback storage', function () {
    $path = storage_path('framework/testing/local-incidents.sqlite');

    File::delete($path);
    config(['incident-fallback.sqlite_path' => $path]);

    app(LocalIncidentStore::class)->record(
        incidentId: 'INC-SQLITE001',
        correlationId: 'corr-123',
        module: 'database',
        action: 'availability_check',
        severity: 'critical',
        safeMessage: 'Estamos teniendo una intermitencia temporal.',
        technicalMessage: 'Connection refused',
        payload: ['path' => 'health/db'],
    );

    $connection = new PDO('sqlite:'.$path);
    $incident = $connection
        ->query("select * from local_incidents where incident_id = 'INC-SQLITE001'")
        ->fetch(PDO::FETCH_ASSOC);

    $payload = json_decode($incident['payload_json'], true);

    expect($incident)->not->toBeFalse()
        ->and($incident['status'])->toBe('pending')
        ->and($incident['module'])->toBe('database')
        ->and($incident['action'])->toBe('availability_check')
        ->and($payload['path'])->toBe('health/db');
});

test('web middleware does not store a local sqlite incident for read requests when primary database is unavailable', function () {
    $path = storage_path('framework/testing/middleware-incidents.sqlite');

    File::delete($path);
    config([
        'incident-fallback.sqlite_path' => $path,
        'incident-fallback.database_check.max_attempts' => 3,
        'incident-fallback.database_check.retry_sleep_ms' => 0,
        'incident-fallback.circuit_breaker.enabled' => false,
    ]);

    Route::get('/middleware-db-fallback-test', fn () => response('ok'))
        ->middleware('web');

    DB::shouldReceive('connection')
        ->times(3)
        ->andThrow(new PDOException('Connection refused'));

    $this->withHeader('X-Correlation-ID', 'corr-db-down')
        ->getJson('/middleware-db-fallback-test')
        ->assertServiceUnavailable()
        ->assertJsonStructure([
            'message',
            'incident_id',
        ]);

    expect(File::exists($path))->toBeFalse();
});

test('web middleware stores a local sqlite incident for write requests when primary database is unavailable', function () {
    $path = storage_path('framework/testing/middleware-write-incidents.sqlite');

    File::delete($path);
    config([
        'incident-fallback.sqlite_path' => $path,
        'incident-fallback.database_check.max_attempts' => 3,
        'incident-fallback.database_check.retry_sleep_ms' => 0,
        'incident-fallback.circuit_breaker.enabled' => false,
    ]);

    Route::post('/middleware-db-fallback-test', fn () => response('ok'))
        ->middleware('web');

    DB::shouldReceive('connection')
        ->times(3)
        ->andThrow(new PDOException('Connection refused'));

    $this->withHeader('X-Correlation-ID', 'corr-db-down')
        ->postJson('/middleware-db-fallback-test', ['name' => 'write'])
        ->assertServiceUnavailable()
        ->assertJsonStructure([
            'message',
            'incident_id',
        ]);

    $connection = new PDO('sqlite:'.$path);
    $incidents = $connection
        ->query('select * from local_incidents')
        ->fetchAll(PDO::FETCH_ASSOC);

    expect($incidents)->toHaveCount(1)
        ->and($incidents[0]['correlation_id'])->toBe('corr-db-down')
        ->and($incidents[0]['module'])->toBe('database')
        ->and($incidents[0]['severity'])->toBe('critical')
        ->and($incidents[0]['payload_json'])->toContain('POST')
        ->and($incidents[0]['payload_json'])->toContain('"attempts":3')
        ->and($incidents[0]['technical_message'])->toContain('Connection refused');
});

test('database circuit breaker short circuits requests without retrying the primary database', function () {
    $incidentPath = storage_path('framework/testing/circuit-breaker-incidents.sqlite');

    File::delete($incidentPath);
    Cache::flush();
    config([
        'incident-fallback.sqlite_path' => $incidentPath,
        'incident-fallback.circuit_breaker.enabled' => true,
        'incident-fallback.circuit_breaker.failure_threshold' => 1,
        'incident-fallback.circuit_breaker.open_seconds' => 30,
    ]);

    app(PrimaryDatabaseCircuitBreaker::class)->recordFailure();

    Route::post('/middleware-db-circuit-open-test', fn () => response('ok'))
        ->middleware('web');

    DB::shouldReceive('connection')->never();

    $this->postJson('/middleware-db-circuit-open-test', ['name' => 'write'])
        ->assertServiceUnavailable()
        ->assertJsonStructure([
            'message',
            'incident_id',
        ]);

    $connection = new PDO('sqlite:'.$incidentPath);
    $incidents = $connection
        ->query('select * from local_incidents')
        ->fetchAll(PDO::FETCH_ASSOC);

    expect($incidents)->toHaveCount(1)
        ->and($incidents[0]['technical_message'])->toBe('Primary database circuit breaker is open.')
        ->and($incidents[0]['payload_json'])->toContain('"attempts":0')
        ->and($incidents[0]['payload_json'])->toContain('"circuit_open":true');
});

test('database circuit breaker returns to normal after the open window expires', function () {
    Cache::flush();
    config([
        'incident-fallback.circuit_breaker.enabled' => true,
        'incident-fallback.circuit_breaker.failure_threshold' => 1,
        'incident-fallback.circuit_breaker.open_seconds' => -1,
    ]);

    $circuitBreaker = app(PrimaryDatabaseCircuitBreaker::class);
    $circuitBreaker->recordFailure();

    expect($circuitBreaker->isOpen())->toBeFalse()
        ->and(Cache::has('circuit_breaker.primary_database'))->toBeFalse();
});

test('database circuit breaker can be reset manually by command', function () {
    Cache::flush();
    config([
        'incident-fallback.circuit_breaker.enabled' => true,
        'incident-fallback.circuit_breaker.failure_threshold' => 1,
        'incident-fallback.circuit_breaker.open_seconds' => 30,
    ]);

    app(PrimaryDatabaseCircuitBreaker::class)->recordFailure();

    expect(Cache::has('circuit_breaker.primary_database'))->toBeTrue();

    $this->artisan('system:database-circuit-reset')
        ->expectsOutput('Primary database circuit breaker reset.')
        ->assertSuccessful();

    expect(Cache::has('circuit_breaker.primary_database'))->toBeFalse();
});

test('database circuit breaker probe closes the circuit when postgres recovers', function () {
    Cache::flush();
    config([
        'incident-fallback.database_check.max_attempts' => 3,
        'incident-fallback.database_check.retry_sleep_ms' => 0,
        'incident-fallback.circuit_breaker.enabled' => true,
        'incident-fallback.circuit_breaker.failure_threshold' => 1,
        'incident-fallback.circuit_breaker.open_seconds' => 30,
    ]);

    app(PrimaryDatabaseCircuitBreaker::class)->recordFailure();

    $pdo = new class
    {
        public function query(string $query): bool
        {
            return $query === 'select 1';
        }
    };

    $connection = new class($pdo)
    {
        public function __construct(private readonly object $pdo) {}

        public function getPdo(): object
        {
            return $this->pdo;
        }
    };

    DB::shouldReceive('connection')
        ->once()
        ->andReturn($connection);

    $this->artisan('system:database-circuit-probe')
        ->expectsOutput('Primary database recovered. Circuit breaker closed.')
        ->assertSuccessful();

    expect(Cache::has('circuit_breaker.primary_database'))->toBeFalse();
});

test('api middleware returns 503 json when primary database is unavailable', function () {
    config([
        'incident-fallback.database_check.max_attempts' => 1,
        'incident-fallback.database_check.retry_sleep_ms' => 0,
        'incident-fallback.circuit_breaker.enabled' => false,
    ]);

    DB::shouldReceive('connection')
        ->once()
        ->andThrow(new PDOException('Connection refused'));

    $this->postJson('/api/supplier-service/bookings', [], [
        'Authorization' => 'Bearer '.config('services.supplier_service.token'),
    ])
        ->assertServiceUnavailable()
        ->assertJsonStructure(['message', 'incident_id']);
});

test('api middleware returns 503 json when circuit breaker is open', function () {
    Cache::flush();
    config([
        'incident-fallback.circuit_breaker.enabled' => true,
        'incident-fallback.circuit_breaker.failure_threshold' => 1,
        'incident-fallback.circuit_breaker.open_seconds' => 30,
    ]);

    app(PrimaryDatabaseCircuitBreaker::class)->recordFailure();

    DB::shouldReceive('connection')->never();

    $this->postJson('/api/supplier-service/bookings', [], [
        'Authorization' => 'Bearer '.config('services.supplier_service.token'),
    ])
        ->assertServiceUnavailable()
        ->assertJsonStructure(['message', 'incident_id']);
});

test('database circuit breaker probe is scheduled every minute', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('system:database-circuit-probe')
        ->assertSuccessful();
});
