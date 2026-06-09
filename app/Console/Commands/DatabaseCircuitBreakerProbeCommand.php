<?php

namespace App\Console\Commands;

use App\Modules\System\Application\Services\PrimaryDatabaseCircuitBreaker;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

#[Signature('system:database-circuit-probe')]
#[Description('Probe PostgreSQL and close the primary database circuit breaker when it recovers.')]
class DatabaseCircuitBreakerProbeCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PrimaryDatabaseCircuitBreaker $circuitBreaker): int
    {
        if (! $circuitBreaker->isOpen()) {
            $this->line('Primary database circuit breaker is closed.');

            return self::SUCCESS;
        }

        try {
            $this->assertDatabaseIsAvailable();
        } catch (Throwable $exception) {
            $circuitBreaker->recordFailure();
            $this->error('Primary database is still unavailable.');

            return self::FAILURE;
        }

        $circuitBreaker->reset();
        $this->info('Primary database recovered. Circuit breaker closed.');

        return self::SUCCESS;
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

        throw $lastException ?? new RuntimeException('Primary database availability probe failed.');
    }
}
