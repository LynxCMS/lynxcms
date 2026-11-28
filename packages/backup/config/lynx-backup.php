<?php

declare(strict_types=1);

return [
    // The database connection to back up; null is the default connection. SQLite and PostgreSQL.
    'connection' => env('LYNX_BACKUP_CONNECTION'),

    // When the scheduler makes a backup: "daily" (at `time`), a cron expression, or empty for never.
    'schedule' => env('LYNX_BACKUP_SCHEDULE', 'daily'),
    'time' => '02:00',

    // The filesystem disks that get a copy of each backup. Add an off-site disk: LYNX_BACKUP_DISKS=local,s3.
    'disks' => explode(',', (string) env('LYNX_BACKUP_DISKS', 'local')),

    // The folder on each disk.
    'path' => 'backups',

    // A password encrypts each zip (AES-256). Keep it outside the server as well.
    'password' => env('LYNX_BACKUP_PASSWORD'),

    // SQLite: "copy" makes a database file with VACUUM INTO, with no binary; "dump" writes SQL
    // text with the sqlite3 binary.
    'sqlite_format' => env('LYNX_BACKUP_SQLITE_FORMAT', 'copy'),

    // Files: off by default, since media can be large. When on, `files` go into each zip, without
    // the backups themselves.
    'include_files' => (bool) env('LYNX_BACKUP_INCLUDE_FILES', false),
    'files' => [storage_path('app')],
];
