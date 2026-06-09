<?php

namespace App\Modules\System\Application\Services;

class DisasterRecoveryReadinessService
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        private readonly HealthCheckService $health,
    ) {}

    /**
     * Build a disaster recovery readiness report.
     *
     * @return array{
     *     status: string,
     *     checks: array<string, array<string, mixed>>,
     *     procedures: array<string, list<string>>
     * }
     */
    public function report(): array
    {
        $checks = [
            'rto' => $this->rto(),
            'rpo' => $this->rpo(),
            'postgresql_backups' => $this->postgresqlBackups(),
            'file_backups' => $this->fileBackups(),
            'recovery_validation' => $this->recoveryValidation(),
        ];

        $statuses = collect($checks)->pluck('status');

        return [
            'status' => $statuses->contains('down')
                ? 'down'
                : ($statuses->contains('degraded') ? 'degraded' : 'healthy'),
            'checks' => $checks,
            'procedures' => config('disaster-recovery.procedures'),
        ];
    }

    /**
     * Validate the RTO objective from the DR plan.
     *
     * @return array<string, mixed>
     */
    private function rto(): array
    {
        $minutes = (int) config('disaster-recovery.objectives.rto_minutes');

        return [
            'status' => $minutes <= 30 ? 'healthy' : 'degraded',
            'minutes' => $minutes,
            'maximum_minutes' => 30,
        ];
    }

    /**
     * Validate the RPO objective from the DR plan.
     *
     * @return array<string, mixed>
     */
    private function rpo(): array
    {
        $minutes = (int) config('disaster-recovery.objectives.rpo_minutes');

        return [
            'status' => $minutes <= 5 ? 'healthy' : 'degraded',
            'minutes' => $minutes,
            'maximum_minutes' => 5,
        ];
    }

    /**
     * Validate PostgreSQL backup policy.
     *
     * @return array<string, mixed>
     */
    private function postgresqlBackups(): array
    {
        $fullBackupFrequency = (string) config('disaster-recovery.backups.postgresql.full_backup_frequency');
        $incrementalBackupFrequency = (string) config('disaster-recovery.backups.postgresql.incremental_backup_frequency');
        $retentionDays = (int) config('disaster-recovery.backups.postgresql.retention_days');

        $isHealthy = $fullBackupFrequency === 'daily'
            && $incrementalBackupFrequency === 'hourly'
            && $retentionDays >= 30;

        return [
            'status' => $isHealthy ? 'healthy' : 'degraded',
            'full_backup_frequency' => $fullBackupFrequency,
            'incremental_backup_frequency' => $incrementalBackupFrequency,
            'retention_days' => $retentionDays,
            'minimum_retention_days' => 30,
        ];
    }

    /**
     * Validate file backup policy.
     *
     * @return array<string, mixed>
     */
    private function fileBackups(): array
    {
        $included = config('disaster-recovery.backups.files.included');
        $retentionDays = (int) config('disaster-recovery.backups.files.retention_days');

        $required = ['storage', 'excel', 'reports'];
        $missing = array_values(array_diff($required, $included));

        return [
            'status' => $missing === [] && $retentionDays >= 90 ? 'healthy' : 'degraded',
            'included' => $included,
            'missing' => $missing,
            'retention_days' => $retentionDays,
            'minimum_retention_days' => 90,
        ];
    }

    /**
     * Validate current recovery health.
     *
     * @return array<string, mixed>
     */
    private function recoveryValidation(): array
    {
        $health = $this->health->all();

        return [
            'status' => $health['status'],
            'health_status' => $health['status'],
            'checks' => $health['checks'],
        ];
    }
}
