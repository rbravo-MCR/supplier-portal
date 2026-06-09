<?php

namespace App\Console\Commands;

use App\Modules\System\Application\Services\HealthCheckService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('system:health-check')]
#[Description('Validate runbook recovery health checks.')]
class SystemHealthCheckCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(HealthCheckService $health): int
    {
        $report = $health->all();

        $this->line("System status: {$report['status']}");
        $this->newLine();

        $this->table(
            ['Check', 'Status', 'Details'],
            collect($report['checks'])
                ->map(fn (array $check, string $name): array => [
                    $name,
                    $check['status'],
                    collect($check)
                        ->except('status')
                        ->map(fn (mixed $value, string $key): string => $key.'='.json_encode($value))
                        ->implode(' '),
                ])
                ->values()
                ->all()
        );

        return $report['status'] === 'healthy' ? self::SUCCESS : self::FAILURE;
    }
}
