<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Audit Driver
    |--------------------------------------------------------------------------
    |
    | Supported: "redis", "sync" (for debugging/local)
    |
    */
    'driver' => env('AUDIT_DRIVER', 'redis'),

    /*
    |--------------------------------------------------------------------------
    | Redis Connection
    |--------------------------------------------------------------------------
    |
    | Connection name defined in config/database.php.
    | It is recommended to use a separate DB index (e.g., db 1).
    |
    */
    'redis' => [
        'connection' => env('AUDIT_REDIS_CONNECTION', 'default'),
        'queue_key'  => env('AUDIT_REDIS_KEY', 'audit_pkg:buffer'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Worker Configuration
    |--------------------------------------------------------------------------
    */
    'worker' => [
        'batch_size'     => (int) env('AUDIT_BATCH_SIZE', 100),
        'flush_interval' => (int) env('AUDIT_FLUSH_INTERVAL', 5), // seconds
        'sleep_ms'       => (int) env('AUDIT_SLEEP_MS', 500),
        'memory_limit'   => (int) env('AUDIT_MEMORY_LIMIT', 128), // MB
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Retention (Days)
    |--------------------------------------------------------------------------
    */
    'prune_days' => 90,
];
