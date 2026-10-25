<?php

return [

    'default' => env('CACHE_STORE', 'database'),

    'stores' => [

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CACHE_CONNECTION'),
            'table' => env('DB_CACHE_TABLE', 'cache'),
            'lock_connection' => env('DB_CACHE_LOCK_CONNECTION'),
            'lock_table' => env('DB_CACHE_LOCK_TABLE'),
        ],

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

    ],

    'prefix' => env('CACHE_PREFIX', 'inventaris_cache_'),

];
