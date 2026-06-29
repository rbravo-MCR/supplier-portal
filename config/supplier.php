<?php

return [
    'max_users' => (int) env('SUPPLIER_MAX_USERS', 3),

    'service' => [
        'allowed_ips' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('SUPPLIER_SERVICE_ALLOWED_IPS', '')),
        ))),
    ],
];
