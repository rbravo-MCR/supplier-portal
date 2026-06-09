<?php

return [
    'sqlite_path' => env('APP_ERROR_SQLITE_PATH', storage_path('app/fallback/incidents.sqlite')),
    'database_check' => [
        'max_attempts' => env('APP_DB_CHECK_MAX_ATTEMPTS', 3),
        'retry_sleep_ms' => env('APP_DB_CHECK_RETRY_SLEEP_MS', 100),
    ],
    'circuit_breaker' => [
        'enabled' => env('APP_DB_CIRCUIT_BREAKER_ENABLED', true),
        'failure_threshold' => env('APP_DB_CIRCUIT_BREAKER_FAILURE_THRESHOLD', 3),
        'open_seconds' => env('APP_DB_CIRCUIT_BREAKER_OPEN_SECONDS', 30),
        'state_path' => env('APP_DB_CIRCUIT_BREAKER_STATE_PATH', storage_path('app/fallback/db-circuit-breaker.json')),
    ],
];
