<?php

return [
    'enabled' => (bool) env('BACKUP_ENABLED', false),
    'dump_binary' => env('BACKUP_DUMP_BINARY', 'mysqldump'),
    'offsite_disk' => env('BACKUP_OFFSITE_DISK'),
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 0),
    'alert_email' => env('BACKUP_ALERT_EMAIL'),
];
