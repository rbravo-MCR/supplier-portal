<?php

namespace App\Modules\System\Application\UseCases;

use App\Models\SystemIncident;
use App\Modules\System\Application\DTOs\RecordRootCauseAnalysisData;

class RecordRootCauseAnalysis
{
    /**
     * Apply a Root Cause Analysis to an existing incident.
     */
    public function handle(RecordRootCauseAnalysisData $data): SystemIncident
    {
        $incident = SystemIncident::query()
            ->where('incident_id', $data->incidentId)
            ->firstOrFail();

        $incident->update([
            'started_at' => $data->startedAt,
            'ended_at' => $data->endedAt,
            'severity' => $data->severity,
            'module' => $data->module,
            'responsible' => $data->responsible,
            'executive_summary' => $data->executiveSummary,
            'affected_users_count' => $data->affectedUsersCount,
            'affected_suppliers' => $data->affectedSuppliers,
            'affected_functionality' => $data->affectedFunctionality,
            'timeline' => $data->timeline,
            'technical_root_cause' => $data->technicalRootCause,
            'organizational_root_cause' => $data->organizationalRootCause,
            'contributing_factors' => $data->contributingFactors,
            'immediate_corrective_actions' => $data->immediateCorrectiveActions,
            'permanent_corrective_actions' => $data->permanentCorrectiveActions,
            'future_prevention' => $data->futurePrevention,
            'architect_approved_by' => $data->architectApprovedBy,
            'technical_lead_approved_by' => $data->technicalLeadApprovedBy,
            'approved_at' => $data->approvedAt,
            'resolved_at' => $data->endedAt,
            'status' => 'resolved',
        ]);

        return $incident->refresh();
    }
}
