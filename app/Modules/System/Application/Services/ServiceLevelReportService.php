<?php

namespace App\Modules\System\Application\Services;

class ServiceLevelReportService
{
    /**
     * Evaluate observed SLI values against configured SLA, SLO, and alert thresholds.
     *
     * @param  array{
     *     uptime_percentage?: float|int,
     *     login_p95_ms?: int,
     *     login_p99_ms?: int,
     *     queries_p95_ms?: int,
     *     queries_p99_ms?: int,
     *     dashboard_p95_ms?: int,
     *     excel_export_p95_ms?: int,
     *     errors_per_minute?: int,
     *     errors_by_module?: array<string, int>,
     *     slow_queries?: int,
     *     active_connections?: int,
     *     locks?: int,
     *     pending_jobs?: int,
     *     failed_jobs?: int,
     *     average_execution_ms?: int,
     *     store_forward_pending?: int,
     *     circuit_breaker_open?: bool,
     *     postgresql_status?: string,
     *     redis_status?: string
     * }  $metrics
     * @return array{
     *     status: string,
     *     checks: array<string, array<string, mixed>>,
     *     alerts: list<array{name: string, threshold: mixed, observed: mixed}>
     * }
     */
    public function evaluate(array $metrics): array
    {
        $checks = [
            'availability' => $this->maximum(
                observed: (float) ($metrics['uptime_percentage'] ?? 100),
                target: (float) config('service-levels.sla.availability_percentage'),
                unit: 'percentage',
            ),
            'login_p95' => $this->minimum(
                observed: (int) ($metrics['login_p95_ms'] ?? 0),
                target: (int) config('service-levels.slo.login.p95_ms'),
                unit: 'ms',
            ),
            'login_p99' => $this->minimum(
                observed: (int) ($metrics['login_p99_ms'] ?? 0),
                target: (int) config('service-levels.slo.login.p99_ms'),
                unit: 'ms',
            ),
            'queries_p95' => $this->minimum(
                observed: (int) ($metrics['queries_p95_ms'] ?? 0),
                target: (int) config('service-levels.slo.queries.p95_ms'),
                unit: 'ms',
            ),
            'queries_p99' => $this->minimum(
                observed: (int) ($metrics['queries_p99_ms'] ?? 0),
                target: (int) config('service-levels.slo.queries.p99_ms'),
                unit: 'ms',
            ),
            'dashboard_p95' => $this->minimum(
                observed: (int) ($metrics['dashboard_p95_ms'] ?? 0),
                target: (int) config('service-levels.slo.dashboard.p95_ms'),
                unit: 'ms',
            ),
            'excel_export_p95' => $this->minimum(
                observed: (int) ($metrics['excel_export_p95_ms'] ?? 0),
                target: (int) config('service-levels.slo.excel_export.p95_ms'),
                unit: 'ms',
            ),
        ];

        $alerts = $this->alerts($metrics);
        $statuses = collect($checks)->pluck('status');

        return [
            'status' => $statuses->contains('breached') || $alerts !== [] ? 'breached' : 'healthy',
            'checks' => $checks,
            'alerts' => $alerts,
        ];
    }

    /**
     * Build an SLI catalog from configuration.
     *
     * @return array<string, list<string>>
     */
    public function catalog(): array
    {
        return config('service-levels.sli');
    }

    /**
     * A lower observed value is better.
     *
     * @return array<string, mixed>
     */
    private function minimum(int $observed, int $target, string $unit): array
    {
        return [
            'status' => $observed <= $target ? 'healthy' : 'breached',
            'observed' => $observed,
            'target' => $target,
            'unit' => $unit,
        ];
    }

    /**
     * A higher observed value is better.
     *
     * @return array<string, mixed>
     */
    private function maximum(float $observed, float $target, string $unit): array
    {
        return [
            'status' => $observed >= $target ? 'healthy' : 'breached',
            'observed' => $observed,
            'target' => $target,
            'unit' => $unit,
        ];
    }

    /**
     * Evaluate alert thresholds.
     *
     * @param  array<string, mixed>  $metrics
     * @return list<array{name: string, threshold: mixed, observed: mixed}>
     */
    private function alerts(array $metrics): array
    {
        $alerts = [];
        $availabilityThreshold = (float) config('service-levels.alerts.availability_below_percentage');

        if ((float) ($metrics['uptime_percentage'] ?? 100) < $availabilityThreshold) {
            $alerts[] = [
                'name' => 'availability_below_target',
                'threshold' => $availabilityThreshold,
                'observed' => $metrics['uptime_percentage'],
            ];
        }

        $failedJobsThreshold = (int) config('service-levels.alerts.failed_jobs_greater_than');

        if ((int) ($metrics['failed_jobs'] ?? 0) > $failedJobsThreshold) {
            $alerts[] = [
                'name' => 'failed_jobs_greater_than_threshold',
                'threshold' => $failedJobsThreshold,
                'observed' => $metrics['failed_jobs'],
            ];
        }

        $storeForwardThreshold = (int) config('service-levels.alerts.store_forward_greater_than');

        if ((int) ($metrics['store_forward_pending'] ?? 0) > $storeForwardThreshold) {
            $alerts[] = [
                'name' => 'store_forward_greater_than_threshold',
                'threshold' => $storeForwardThreshold,
                'observed' => $metrics['store_forward_pending'],
            ];
        }

        if (($metrics['circuit_breaker_open'] ?? false) === true) {
            $alerts[] = [
                'name' => 'circuit_breaker_open',
                'threshold' => false,
                'observed' => true,
            ];
        }

        if (($metrics['postgresql_status'] ?? 'healthy') === 'down') {
            $alerts[] = [
                'name' => 'postgresql_down',
                'threshold' => 'healthy',
                'observed' => 'down',
            ];
        }

        if (($metrics['redis_status'] ?? 'healthy') === 'down') {
            $alerts[] = [
                'name' => 'redis_down',
                'threshold' => 'healthy',
                'observed' => 'down',
            ];
        }

        return $alerts;
    }
}
