<?php

return [
    'enabled' => (bool) env('SLA_KPI_ENABLED', false),
    'policy_approved' => (bool) env('SLA_POLICY_APPROVED', false),
    'acceptance_approved' => (bool) env('SLA_KPI_ACCEPTANCE_APPROVED', false),
    'effective_at' => env('SLA_POLICY_EFFECTIVE_AT'),
    'priority_mapping' => [],
    'targets_work_seconds' => [
        'P1' => ['response' => 3600, 'resolution' => 28800],
        'P2' => ['response' => 14400, 'resolution' => 86400],
        'P3' => ['response' => 28800, 'resolution' => 144000],
        'P4' => ['response' => 57600, 'resolution' => 288000],
    ],
    'calendar' => [
        'timezone' => 'Asia/Jakarta',
        'working_days' => [1, 2, 3, 4, 5],
        'work_windows' => [['08:30', '12:00'], ['13:00', '17:30']],
        'verified' => false,
        'schedule_version' => null,
        'verified_from' => null,
        'verified_through' => null,
        'holidays' => [
            'source' => null,
            'version' => null,
            'verified' => false,
            'verified_from' => null,
            'verified_through' => null,
            'dates' => [],
        ],
    ],
];
