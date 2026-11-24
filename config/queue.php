<?php

return [

    'default' => env('QUEUE_CONNECTION', 'database'),

    'connections' => [

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CONNECTION', 'pgsql'),
            'table' => env('QUEUE_TABLE', 'jobs'),
            'queue' => env('QUEUE_QUEUE', 'default'),
            'failed_after' => env('QUEUE_FAILED_AFTER', 90),
            'after_commit' => true,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('QUEUE_REDIS_CONNECTION', 'default'),
            'queue' => env('QUEUE_QUEUE', 'default'),
            'retry_after' => env('QUEUE_RETRY_AFTER', 90),
            'block_for' => null,
            'after_commit' => true,
        ],

        'sync' => [
            'driver' => 'sync',
        ],

    ],

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'pgsql'),
        'table' => env('QUEUE_FAILED_TABLE', 'failed_jobs'),
    ],

];
