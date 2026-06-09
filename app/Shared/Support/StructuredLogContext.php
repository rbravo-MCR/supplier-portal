<?php

namespace App\Shared\Support;

use App\Models\User;
use Illuminate\Support\Str;

class StructuredLogContext
{
    /**
     * Build standard structured log context.
     *
     * @return array{
     *     incident_id: string|null,
     *     correlation_id: string,
     *     request_id: string,
     *     supplier_id: int|null,
     *     user_id: int|null,
     *     module: string,
     *     action: string
     * }
     */
    public static function for(
        string $module,
        string $action,
        ?string $incidentId = null,
        ?User $user = null,
        ?string $correlationId = null,
        ?string $requestId = null,
    ): array {
        return [
            'incident_id' => $incidentId,
            'correlation_id' => $correlationId ?? (string) Str::uuid(),
            'request_id' => $requestId ?? request()->headers->get('X-Request-Id', (string) Str::uuid()),
            'supplier_id' => $user?->supplier_id,
            'user_id' => $user?->id,
            'module' => $module,
            'action' => $action,
        ];
    }
}
