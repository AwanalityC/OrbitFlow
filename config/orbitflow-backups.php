<?php

return [
    // Stored under storage/app/private; never expose this directory as a public disk.
    'directory' => env('ORBITFLOW_BACKUP_DIRECTORY', 'orbitflow-backups'),

    // Old generated .zip archives are removed after a successful backup.
    'retention_days' => (int) env('ORBITFLOW_BACKUP_RETENTION_DAYS', 30),

    // Daily scheduler time in Asia/Jakarta.
    'schedule_time' => env('ORBITFLOW_BACKUP_SCHEDULE_TIME', '01:00'),

    // Optional absolute paths. If empty, PATH and common XAMPP locations are searched.
    'mysql_dump_binary' => env('ORBITFLOW_MYSQL_DUMP_BINARY'),
    'mysql_binary' => env('ORBITFLOW_MYSQL_BINARY'),

    'process_timeout' => (int) env('ORBITFLOW_BACKUP_PROCESS_TIMEOUT', 1800),
];
