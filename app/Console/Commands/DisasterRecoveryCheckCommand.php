<?php

namespace App\Console\Commands;

use App\Modules\System\Application\Services\DisasterRecoveryReadinessService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('system:disaster-recovery-check')]
#[Description('Validate disaster recovery objectives, backup policy, and recovery health.')]
class DisasterRecoveryCheckCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DisasterRecoveryReadinessService $readiness): int
    {
        $report = $readiness->report();

        $this->line("Disaster recovery status: {$report['status']}");
        $this->newLine();

        $this->table(
            ['Check', 'Status', 'Details'],
            collect($report['checks'])
                ->map(fn (array $check, string $name): array => [
                    $name,
                    $check['status'],
                    collect($check)
                        ->except(['status', 'checks'])
                        ->map(fn (mixed $value, string $key): string => $key.'='.json_encode($value))
                        ->implode(' '),
                ])
                ->values()
                ->all()
        );

        return $report['status'] === 'healthy' ? self::SUCCESS : self::FAILURE;
    }
}
