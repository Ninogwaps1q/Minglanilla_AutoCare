<?php

return [
    'enabled' => (bool) env('BACKUP_ENABLED', false),
    'dump_binary' => env('BACKUP_DUMP_BINARY', 'mysqldump'),
];
