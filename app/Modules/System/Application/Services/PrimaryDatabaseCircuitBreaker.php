<?php

namespace App\Modules\System\Application\Services;

use Illuminate\Support\Facades\Cache;

class PrimaryDatabaseCircuitBreaker
{
    private const CACHE_KEY = 'circuit_breaker.primary_database';

    private const CACHE_TTL_SECONDS = 3600;

    /**
     * Determine whether database checks should be short-circuited.
     */
    public function isOpen(): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $state = $this->state();
        $openedUntil = $state['opened_until'] ?? null;

        if ($openedUntil === null) {
            return false;
        }

        if (now()->lessThan($openedUntil)) {
            return true;
        }

        $this->reset();

        return false;
    }

    /**
     * Record a failed primary database check.
     */
    public function recordFailure(): void
    {
        if (! $this->enabled()) {
            return;
        }

        $state = $this->state();
        $failures = ((int) ($state['failures'] ?? 0)) + 1;
        $openedUntil = null;

        if ($failures >= (int) config('incident-fallback.circuit_breaker.failure_threshold')) {
            $openedUntil = now()
                ->addSeconds((int) config('incident-fallback.circuit_breaker.open_seconds'))
                ->toISOString();
        }

        $this->write([
            'failures' => $failures,
            'opened_until' => $openedUntil,
            'updated_at' => now()->toISOString(),
        ]);
    }

    /**
     * Reset circuit state after a successful database check.
     */
    public function recordSuccess(): void
    {
        $this->reset();
    }

    /**
     * Get current circuit state.
     *
     * @return array{failures: int, opened_until: string|null, updated_at: string|null}
     */
    public function state(): array
    {
        $state = Cache::get(self::CACHE_KEY);

        if ($state === null) {
            return [
                'failures' => 0,
                'opened_until' => null,
                'updated_at' => null,
            ];
        }

        return [
            'failures' => (int) ($state['failures'] ?? 0),
            'opened_until' => $state['opened_until'] ?? null,
            'updated_at' => $state['updated_at'] ?? null,
        ];
    }

    /**
     * Clear circuit state.
     */
    public function reset(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Determine whether circuit breaker is enabled.
     */
    private function enabled(): bool
    {
        return (bool) config('incident-fallback.circuit_breaker.enabled');
    }

    /**
     * Persist state to cache.
     *
     * @param  array<string, mixed>  $state
     */
    private function write(array $state): void
    {
        Cache::put(self::CACHE_KEY, $state, self::CACHE_TTL_SECONDS);
    }
}
