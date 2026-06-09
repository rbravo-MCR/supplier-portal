<?php

namespace App\Console\Commands;

use App\Modules\System\Application\Services\ServiceLevelReportService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('system:service-level-check
    {--uptime=100 : Observed uptime percentage}
    {--login-p95=0 : Login p95 latency in milliseconds}
    {--login-p99=0 : Login p99 latency in milliseconds}
    {--queries-p95=0 : Query p95 latency in milliseconds}
    {--queries-p99=0 : Query p99 latency in milliseconds}
    {--dashboard-p95=0 : Dashboard p95 latency in milliseconds}
    {--excel-export-p95=0 : Excel export p95 duration in milliseconds}
    {--failed-jobs=0 : Failed jobs count}
    {--store-forward=0 : Store & Forward pending operations}
    {--circuit-breaker-open : Circuit breaker is open}
    {--postgresql-down : PostgreSQL is down}
    {--redis-down : Redis is down}')]
#[Description('Validate observed service levels against SLA, SLO, SLI, and alert thresholds.')]
class ServiceLevelCheckCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ServiceLevelReportService $serviceLevels): int
    {
        $report = $serviceLevels->evaluate([
            'uptime_percentage' => (float) $this->option('uptime'),
            'login_p95_ms' => (int) $this->option('login-p95'),
            'login_p99_ms' => (int) $this->option('login-p99'),
            'queries_p95_ms' => (int) $this->option('queries-p95'),
            'queries_p99_ms' => (int) $this->option('queries-p99'),
            'dashboard_p95_ms' => (int) $this->option('dashboard-p95'),
            'excel_export_p95_ms' => (int) $this->option('excel-export-p95'),
            'failed_jobs' => (int) $this->option('failed-jobs'),
            'store_forward_pending' => (int) $this->option('store-forward'),
            'circuit_breaker_open' => (bool) $this->option('circuit-breaker-open'),
            'postgresql_status' => $this->option('postgresql-down') ? 'down' : 'healthy',
            'redis_status' => $this->option('redis-down') ? 'down' : 'healthy',
        ]);

        $this->line("Service level status: {$report['status']}");
        $this->newLine();

        $this->table(
            ['Check', 'Status', 'Observed', 'Target', 'Unit'],
            collect($report['checks'])
                ->map(fn (array $check, string $name): array => [
                    $name,
                    $check['status'],
                    (string) $check['observed'],
                    (string) $check['target'],
                    $check['unit'],
                ])
                ->values()
                ->all()
        );

        if ($report['alerts'] !== []) {
            $this->newLine();
            $this->warn('Alerts');

            $this->table(
                ['Alert', 'Observed', 'Threshold'],
                collect($report['alerts'])
                    ->map(fn (array $alert): array => [
                        $alert['name'],
                        json_encode($alert['observed']),
                        json_encode($alert['threshold']),
                    ])
                    ->all()
            );
        }

        return $report['status'] === 'healthy' ? self::SUCCESS : self::FAILURE;
    }
}
