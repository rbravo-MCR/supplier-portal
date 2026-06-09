<?php

namespace App\Models;

use Database\Factories\SystemIncidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'incident_id',
    'correlation_id',
    'supplier_id',
    'user_id',
    'module',
    'action',
    'severity',
    'exception_class',
    'safe_message',
    'technical_message',
    'stack_trace',
    'payload_json',
    'status',
    'started_at',
    'ended_at',
    'responsible',
    'executive_summary',
    'affected_users_count',
    'affected_suppliers',
    'affected_functionality',
    'timeline',
    'technical_root_cause',
    'organizational_root_cause',
    'contributing_factors',
    'immediate_corrective_actions',
    'permanent_corrective_actions',
    'future_prevention',
    'architect_approved_by',
    'technical_lead_approved_by',
    'approved_at',
    'resolved_at',
])]
class SystemIncident extends Model
{
    /** @use HasFactory<SystemIncidentFactory> */
    use HasFactory;

    /**
     * Get the affected supplier.
     *
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the affected user.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'affected_users_count' => 'integer',
            'affected_suppliers' => 'array',
            'affected_functionality' => 'array',
            'timeline' => 'array',
            'contributing_factors' => 'array',
            'immediate_corrective_actions' => 'array',
            'permanent_corrective_actions' => 'array',
            'future_prevention' => 'array',
            'approved_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
