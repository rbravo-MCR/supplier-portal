<?php

return [
    'sla' => [
        'availability_percentage' => env('SERVICE_LEVEL_SLA_AVAILABILITY_PERCENTAGE', 99.9),
        'monthly_downtime_minutes' => env('SERVICE_LEVEL_SLA_MONTHLY_DOWNTIME_MINUTES', 43),
    ],

    'slo' => [
        'login' => [
            'p95_ms' => env('SERVICE_LEVEL_SLO_LOGIN_P95_MS', 1000),
            'p99_ms' => env('SERVICE_LEVEL_SLO_LOGIN_P99_MS', 2000),
        ],
        'queries' => [
            'p95_ms' => env('SERVICE_LEVEL_SLO_QUERIES_P95_MS', 500),
            'p99_ms' => env('SERVICE_LEVEL_SLO_QUERIES_P99_MS', 1000),
        ],
        'dashboard' => [
            'p95_ms' => env('SERVICE_LEVEL_SLO_DASHBOARD_P95_MS', 2000),
        ],
        'excel_export' => [
            'p95_ms' => env('SERVICE_LEVEL_SLO_EXCEL_EXPORT_P95_MS', 300000),
        ],
    ],

    'sli' => [
        'availability' => ['uptime_percentage'],
        'errors' => ['errors_per_minute', 'errors_by_module'],
        'database' => ['slow_queries', 'active_connections', 'locks'],
        'queue' => ['pending_jobs', 'failed_jobs', 'average_execution_ms'],
    ],

    'alerts' => [
        'availability_below_percentage' => env('SERVICE_LEVEL_ALERT_AVAILABILITY_BELOW_PERCENTAGE', 99.9),
        'failed_jobs_greater_than' => env('SERVICE_LEVEL_ALERT_FAILED_JOBS_GREATER_THAN', 20),
        'store_forward_greater_than' => env('SERVICE_LEVEL_ALERT_STORE_FORWARD_GREATER_THAN', 100),
        'circuit_breaker_open' => true,
        'postgresql_down' => true,
        'redis_down' => true,
    ],
];
