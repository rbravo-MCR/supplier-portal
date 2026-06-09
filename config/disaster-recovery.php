<?php

return [
    'objectives' => [
        'rto_minutes' => env('DISASTER_RECOVERY_RTO_MINUTES', 30),
        'rpo_minutes' => env('DISASTER_RECOVERY_RPO_MINUTES', 5),
    ],

    'backups' => [
        'postgresql' => [
            'full_backup_frequency' => env('DISASTER_RECOVERY_POSTGRES_FULL_BACKUP_FREQUENCY', 'daily'),
            'incremental_backup_frequency' => env('DISASTER_RECOVERY_POSTGRES_INCREMENTAL_BACKUP_FREQUENCY', 'hourly'),
            'retention_days' => env('DISASTER_RECOVERY_POSTGRES_RETENTION_DAYS', 30),
        ],
        'files' => [
            'included' => [
                'storage',
                'excel',
                'reports',
            ],
            'retention_days' => env('DISASTER_RECOVERY_FILES_RETENTION_DAYS', 90),
        ],
    ],

    'procedures' => [
        'postgresql_corrupt' => [
            'block_writes',
            'activate_degraded_mode',
            'restore_latest_backup',
            'verify_integrity',
            'resume_operation',
        ],
        'redis_lost' => [
            'restore_redis',
            'restart_horizon',
            'validate_queues',
            'reprocess_failed_jobs',
        ],
        'server_down' => [
            'start_secondary_server',
            'restore_configuration',
            'restore_backups',
            'validate_health_checks',
        ],
        'sqlite_store_forward_saturated' => [
            'check_postgresql',
            'run_synchronization',
            'validate_idempotency',
            'clean_synced_records',
        ],
    ],
];
