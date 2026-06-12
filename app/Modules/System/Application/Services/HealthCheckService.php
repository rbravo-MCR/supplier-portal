<?php

namespace App\Modules\System\Application\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class HealthCheckService
{
    private const OUTBOX_PENDING_THRESHOLD = 100;

    private const CACHE_TTL_SECONDS = 60;

    private const FAILURE_MESSAGE = 'Health check failed. Review application logs for details.';

    /**
     * Get aggregate system health.
     */
    public function all(): array
    {
        return $this->remember('health.checks.all', function () {
            $checks = [
                'db' => $this->database(),
                'redis' => $this->redis(),
                'queue' => $this->queue(),
                'storage' => $this->storage(),
                'outbox' => $this->outbox(),
                'failed_jobs' => $this->failedJobs(),
            ];

            $statuses = collect($checks)->pluck('status');

            return [
                'status' => $statuses->contains('down')
                    ? 'down'
                    : ($statuses->contains('degraded') ? 'degraded' : 'healthy'),
                'checks' => $checks,
            ];
        });
    }

    /**
     * Check database availability.
     */
    public function database(): array
    {
        return $this->remember('health.check.db', function () {
            try {
                DB::connection()->getPdo();

                return ['status' => 'healthy'];
            } catch (Throwable $exception) {
                report($exception);

                return [
                    'status' => 'down',
                    'message' => self::FAILURE_MESSAGE,
                ];
            }
        });
    }

    /**
     * Check Redis availability.
     */
    public function redis(): array
    {
        return $this->remember('health.check.redis', function () {
            try {
                if ($this->redisClientIsUnavailable()) {
                    return [
                        'status' => 'degraded',
                        'message' => 'Redis client is not installed for the configured driver.',
                    ];
                }

                Redis::connection()->ping();

                return ['status' => 'healthy'];
            } catch (Throwable $exception) {
                report($exception);

                return [
                    'status' => 'degraded',
                    'message' => self::FAILURE_MESSAGE,
                ];
            }
        });
    }

    /**
     * Check queue configuration.
     */
    public function queue(): array
    {
        return $this->remember('health.check.queue', function () {
            try {
                Queue::connection()->getConnectionName();

                return ['status' => 'healthy'];
            } catch (Throwable $exception) {
                report($exception);

                return [
                    'status' => 'degraded',
                    'message' => self::FAILURE_MESSAGE,
                ];
            }
        });
    }

    /**
     * Check default storage availability.
     */
    public function storage(): array
    {
        return $this->remember('health.check.storage', function () {
            try {
                Storage::disk()->exists('.');

                return ['status' => 'healthy'];
            } catch (Throwable $exception) {
                report($exception);

                return [
                    'status' => 'down',
                    'message' => self::FAILURE_MESSAGE,
                ];
            }
        });
    }

    /**
     * Check Store & Forward backlog.
     */
    public function outbox(): array
    {
        return $this->remember('health.check.outbox', function () {
            try {
                if (! Schema::hasTable('outbox_events')) {
                    return [
                        'status' => 'degraded',
                        'pending_operations' => null,
                        'threshold' => self::OUTBOX_PENDING_THRESHOLD,
                        'message' => 'Required health table is missing.',
                    ];
                }

                $pendingOperations = DB::table('outbox_events')
                    ->where('status', 'pending')
                    ->count();

                return [
                    'status' => $pendingOperations > self::OUTBOX_PENDING_THRESHOLD ? 'degraded' : 'healthy',
                    'pending_operations' => $pendingOperations,
                    'threshold' => self::OUTBOX_PENDING_THRESHOLD,
                ];
            } catch (Throwable $exception) {
                report($exception);

                return [
                    'status' => 'degraded',
                    'pending_operations' => null,
                    'threshold' => self::OUTBOX_PENDING_THRESHOLD,
                    'message' => self::FAILURE_MESSAGE,
                ];
            }
        });
    }

    /**
     * Check failed queue jobs.
     */
    public function failedJobs(): array
    {
        return $this->remember('health.check.failed_jobs', function () {
            try {
                if (! Schema::hasTable('failed_jobs')) {
                    return [
                        'status' => 'degraded',
                        'failed' => null,
                        'message' => 'Required health table is missing.',
                    ];
                }

                $failedJobs = DB::table('failed_jobs')->count();

                return [
                    'status' => $failedJobs > 0 ? 'degraded' : 'healthy',
                    'failed' => $failedJobs,
                ];
            } catch (Throwable $exception) {
                report($exception);

                return [
                    'status' => 'degraded',
                    'failed' => null,
                    'message' => self::FAILURE_MESSAGE,
                ];
            }
        });
    }

    /**
     * Cache health data when the configured cache store is available.
     *
     * @return array<string, mixed>
     */
    private function remember(string $key, callable $resolver): array
    {
        try {
            return Cache::remember($key, self::CACHE_TTL_SECONDS, $resolver);
        } catch (Throwable) {
            return $resolver();
        }
    }

    /**
     * Determine whether the configured Redis client can be loaded.
     */
    private function redisClientIsUnavailable(): bool
    {
        return match (config('database.redis.client')) {
            'phpredis' => ! class_exists('Redis'),
            'predis' => ! class_exists('Predis\Client'),
            default => false,
        };
    }
}
