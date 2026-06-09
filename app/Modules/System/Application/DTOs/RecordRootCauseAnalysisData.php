<?php

namespace App\Modules\System\Application\DTOs;

use Carbon\CarbonInterface;

class RecordRootCauseAnalysisData
{
    /**
     * Create a new class instance.
     *
     * @param  list<string>  $affectedSuppliers
     * @param  list<string>  $affectedFunctionality
     * @param  list<array{time: string, event: string}>  $timeline
     * @param  list<string>  $contributingFactors
     * @param  list<string>  $immediateCorrectiveActions
     * @param  list<string>  $permanentCorrectiveActions
     * @param  list<string>  $futurePrevention
     */
    public function __construct(
        public readonly string $incidentId,
        public readonly CarbonInterface $startedAt,
        public readonly CarbonInterface $endedAt,
        public readonly string $severity,
        public readonly string $module,
        public readonly string $responsible,
        public readonly string $executiveSummary,
        public readonly int $affectedUsersCount,
        public readonly array $affectedSuppliers,
        public readonly array $affectedFunctionality,
        public readonly array $timeline,
        public readonly string $technicalRootCause,
        public readonly string $organizationalRootCause,
        public readonly array $contributingFactors,
        public readonly array $immediateCorrectiveActions,
        public readonly array $permanentCorrectiveActions,
        public readonly array $futurePrevention,
        public readonly string $architectApprovedBy,
        public readonly string $technicalLeadApprovedBy,
        public readonly CarbonInterface $approvedAt,
    ) {}
}
