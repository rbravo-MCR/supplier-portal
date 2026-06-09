<?php

namespace App\Modules\System\Application\Services;

use Illuminate\Support\Facades\File;
use PDO;

class LocalIncidentStore
{
    /**
     * Store an incident in the local SQLite fallback database.
     *
     * @param  array<string, mixed>  $payload
     */
    public function record(
        string $incidentId,
        ?string $correlationId,
        string $module,
        string $action,
        string $severity,
        string $safeMessage,
        ?string $technicalMessage = null,
        ?array $payload = null,
    ): void {
        $connection = $this->connection();
        $this->ensureSchema($connection);

        $statement = $connection->prepare(
            'insert or ignore into local_incidents (
                incident_id,
                correlation_id,
                supplier_id,
                user_id,
                module,
                action,
                severity,
                safe_message,
                technical_message,
                payload_json,
                status,
                attempts,
                created_at,
                synced_at
            ) values (
                :incident_id,
                :correlation_id,
                :supplier_id,
                :user_id,
                :module,
                :action,
                :severity,
                :safe_message,
                :technical_message,
                :payload_json,
                :status,
                :attempts,
                :created_at,
                :synced_at
            )'
        );

        $statement->execute([
            'incident_id' => $incidentId,
            'correlation_id' => $correlationId,
            'supplier_id' => $payload['supplier_id'] ?? null,
            'user_id' => $payload['user_id'] ?? null,
            'module' => $module,
            'action' => $action,
            'severity' => $severity,
            'safe_message' => $safeMessage,
            'technical_message' => $technicalMessage,
            'payload_json' => json_encode($payload ?? []),
            'status' => 'pending',
            'attempts' => 0,
            'created_at' => now()->toISOString(),
            'synced_at' => null,
        ]);
    }

    /**
     * Open the local SQLite database.
     */
    private function connection(): PDO
    {
        $path = (string) config('incident-fallback.sqlite_path');

        File::ensureDirectoryExists(dirname($path));

        return new PDO('sqlite:'.$path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    /**
     * Ensure the fallback table exists.
     */
    private function ensureSchema(PDO $connection): void
    {
        $connection->exec(
            'create table if not exists local_incidents (
                id integer primary key autoincrement,
                incident_id text unique not null,
                correlation_id text null,
                supplier_id integer null,
                user_id integer null,
                module text not null,
                action text not null,
                severity text not null,
                safe_message text not null,
                technical_message text null,
                payload_json text null,
                status text not null,
                attempts integer not null default 0,
                created_at text not null,
                synced_at text null
            )'
        );
    }
}
