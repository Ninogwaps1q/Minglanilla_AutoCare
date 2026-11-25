<?php

return [
    'alert_email' => env('MONITOR_ALERT_EMAIL'),
    'require_queue_heartbeat' => env('MONITOR_QUEUE_HEARTBEAT', false),
];
