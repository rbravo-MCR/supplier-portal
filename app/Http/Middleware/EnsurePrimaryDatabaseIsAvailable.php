<?php

namespace App\Http\Middleware;

use App\Modules\System\Application\Services\LocalIncidentStore;
use App\Modules\System\Application\Services\PrimaryDatabaseCircuitBreaker;
use App\Shared\Support\IncidentId;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnsurePrimaryDatabaseIsAvailable
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        private readonly LocalIncidentStore $incidents,
        private readonly PrimaryDatabaseCircuitBreaker $circuitBreaker,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->circuitBreaker->isOpen()) {
            return $this->unavailableResponse(
                request: $request,
                technicalMessage: 'Primary database circuit breaker is open.',
                attempts: 0,
            );
        }

        try {
            $this->assertDatabaseIsAvailable();
            $this->circuitBreaker->recordSuccess();
        } catch (Throwable $exception) {
            $this->circuitBreaker->recordFailure();

            return $this->unavailableResponse(
                request: $request,
                technicalMessage: $exception->getMessage(),
                attempts: (int) config('incident-fallback.database_check.max_attempts'),
            );
        }

        return $next($request);
    }

    /**
     * Check the primary database with bounded retries.
     */
    private function assertDatabaseIsAvailable(): void
    {
        $maxAttempts = max(1, (int) config('incident-fallback.database_check.max_attempts'));
        $sleepMicroseconds = max(0, (int) config('incident-fallback.database_check.retry_sleep_ms')) * 1000;
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                DB::connection()->getPdo()->query('select 1');

                return;
            } catch (Throwable $exception) {
                $lastException = $exception;

                if ($attempt < $maxAttempts && $sleepMicroseconds > 0) {
                    usleep($sleepMicroseconds);
                }
            }
        }

        throw $lastException ?? new RuntimeException('Primary database availability check failed.');
    }

    /**
     * Build the database unavailable response and store write incidents locally.
     */
    private function unavailableResponse(Request $request, string $technicalMessage, int $attempts): Response
    {
        $incidentId = IncidentId::generate();

        if (! $request->isMethodSafe()) {
            $this->incidents->record(
                incidentId: $incidentId,
                correlationId: $request->headers->get('X-Correlation-ID'),
                module: 'database',
                action: 'availability_check',
                severity: 'critical',
                safeMessage: 'Estamos teniendo una intermitencia temporal.',
                technicalMessage: $technicalMessage,
                payload: [
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'ip' => $request->ip(),
                    'attempts' => $attempts,
                    'circuit_open' => $this->circuitBreaker->isOpen(),
                ],
            );
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Estamos teniendo una intermitencia temporal.',
                'incident_id' => $incidentId,
            ], 503);
        }

        return response(
            "Estamos teniendo una intermitencia temporal.\nCódigo de seguimiento: {$incidentId}",
            503,
            ['Content-Type' => 'text/plain; charset=UTF-8'],
        );
    }
}
