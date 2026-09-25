<?php

return [
    // Private directory outside every filesystem disk root, so it is never web-served.
    'path' => env('BACKUP_PATH', storage_path('app/backups')),

    // Approved policy: daily backups kept 7–30 days. Final value is chosen at deployment.
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),
    'daily_at' => env('BACKUP_DAILY_AT', '01:30'),
    'verify_weekly_at' => env('BACKUP_VERIFY_WEEKLY_AT', '03:00'),

    // Full paths are needed where the MySQL client tools are not on PATH (e.g. Windows).
    'mysqldump_binary' => env('BACKUP_MYSQLDUMP_BINARY', 'mysqldump'),
    'mysql_binary' => env('BACKUP_MYSQL_BINARY', 'mysql'),

    // Restore checks load a backup into this scratch database and wipe it afterwards.
    // The name must end in _restore_check; it is never the live or test database.
    'restore' => [
        'database' => env('BACKUP_RESTORE_DATABASE', env('DB_DATABASE', 'mfbms').'_restore_check'),
        'username' => env('BACKUP_RESTORE_USERNAME', env('DB_USERNAME')),
        'password' => env('BACKUP_RESTORE_PASSWORD', env('DB_PASSWORD')),
    ],
];
