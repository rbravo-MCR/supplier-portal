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

    /**
     * Get aggregate system health.
     */
    public function all(): array
    {
        return Cache::remember('health.checks.all', self::CACHE_TTL_SECONDS, function () {
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
        return Cache::remember('health.check.db', self::CACHE_TTL_SECONDS, function () {
            try {
                DB::connection()->getPdo();

                return ['status' => 'healthy'];
            } catch (Throwable $exception) {
                return [
                    'status' => 'down',
                    'error' => $exception->getMessage(),
                ];
            }
        });
    }

    /**
     * Check Redis availability.
     */
    public function redis(): array
    {
        return Cache::remember('health.check.redis', self::CACHE_TTL_SECONDS, function () {
            try {
                Redis::connection()->ping();

                return ['status' => 'healthy'];
            } catch (Throwable $exception) {
                return [
                    'status' => 'degraded',
                    'error' => $exception->getMessage(),
                ];
            }
        });
    }

    /**
     * Check queue configuration.
     */
    public function queue(): array
    {
        return Cache::remember('health.check.queue', self::CACHE_TTL_SECONDS, function () {
            try {
                Queue::connection()->getConnectionName();

                return ['status' => 'healthy'];
            } catch (Throwable $exception) {
                return [
                    'status' => 'degraded',
                    'error' => $exception->getMessage(),
                ];
            }
        });
    }

    /**
     * Check default storage availability.
     */
    public function storage(): array
    {
        return Cache::remember('health.check.storage', self::CACHE_TTL_SECONDS, function () {
            try {
                Storage::disk()->exists('.');

                return ['status' => 'healthy'];
            } catch (Throwable $exception) {
                return [
                    'status' => 'down',
                    'error' => $exception->getMessage(),
                ];
            }
        });
    }

    /**
     * Check Store & Forward backlog.
     */
    public function outbox(): array
    {
        return Cache::remember('health.check.outbox', self::CACHE_TTL_SECONDS, function () {
            try {
                if (! Schema::hasTable('outbox_events')) {
                    return [
                        'status' => 'degraded',
                        'pending_operations' => null,
                        'threshold' => self::OUTBOX_PENDING_THRESHOLD,
                        'error' => 'outbox_events table is missing',
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
                return [
                    'status' => 'degraded',
                    'pending_operations' => null,
                    'threshold' => self::OUTBOX_PENDING_THRESHOLD,
                    'error' => $exception->getMessage(),
                ];
            }
        });
    }

    /**
     * Check failed queue jobs.
     */
    public function failedJobs(): array
    {
        return Cache::remember('health.check.failed_jobs', self::CACHE_TTL_SECONDS, function () {
            try {
                if (! Schema::hasTable('failed_jobs')) {
                    return [
                        'status' => 'degraded',
                        'failed' => null,
                        'error' => 'failed_jobs table is missing',
                    ];
                }

                $failedJobs = DB::table('failed_jobs')->count();

                return [
                    'status' => $failedJobs > 0 ? 'degraded' : 'healthy',
                    'failed' => $failedJobs,
                ];
            } catch (Throwable $exception) {
                return [
                    'status' => 'degraded',
                    'failed' => null,
                    'error' => $exception->getMessage(),
                ];
            }
        });
    }
}
