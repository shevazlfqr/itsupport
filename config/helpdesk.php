<?php

return [
    'statuses' => [
        'new' => 'Baru',
        'assigned' => 'Ditugaskan',
        'in_progress' => 'Sedang dikerjakan',
        'completed' => 'Selesai',
        'closed' => 'Ditutup',
        'cancelled' => 'Dibatalkan',
    ],

    'priorities' => [
        'low' => 'Rendah',
        'medium' => 'Sedang',
        'high' => 'Tinggi',
        'urgent' => 'Urgent',
    ],

    'categories' => [
        'Perangkat',
        'Software',
        'Jaringan',
        'Akun & Akses',
        'Email',
        'Lainnya',
    ],

    'roles' => [
        'employee' => 'Karyawan',
        'it' => 'Tim IT',
        'admin' => 'Admin',
    ],

    'status_transitions' => [
        'new' => ['new', 'assigned', 'cancelled'],
        'assigned' => ['assigned', 'in_progress', 'cancelled', 'new'],
        'in_progress' => ['in_progress', 'completed', 'cancelled'],
        'completed' => ['completed', 'closed', 'in_progress'],
        'closed' => ['closed'],
        'cancelled' => ['cancelled'],
    ],

    'sla_hours' => [
        'urgent' => 4,
        'high' => 8,
        'medium' => 24,
        'low' => 72,
    ],

    'uploads' => [
        'max_files' => 5,
        'max_size_kb' => 10240,
        'mimes' => ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'],
    ],
];
