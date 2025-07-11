<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SMS Proxy API Configuration
    |--------------------------------------------------------------------------
    */

    'api' => [
        'base_url' => env('SMS_API_BASE_URL', 'https://postback-sms.com/api/'),
        'timeout' => env('SMS_API_TIMEOUT', 5),
        'connect_timeout' => env('SMS_API_CONNECT_TIMEOUT', 2),
        'max_retries' => env('SMS_API_MAX_RETRIES', 3),
    ],

    'cache' => [
        'default_ttl' => env('SMS_CACHE_TTL', 300), // 5 minutes
        'sms_ttl' => env('SMS_CACHE_SMS_TTL', 10), // 10 seconds
        'status_ttl' => env('SMS_CACHE_STATUS_TTL', 15), // 15 seconds
    ],

    'rate_limit' => [
        'get_number' => env('SMS_RATE_LIMIT_GET_NUMBER', 100),
        'get_sms' => env('SMS_RATE_LIMIT_GET_SMS', 200),
        'cancel_number' => env('SMS_RATE_LIMIT_CANCEL_NUMBER', 50),
        'get_status' => env('SMS_RATE_LIMIT_GET_STATUS', 300),
        'window' => env('SMS_RATE_LIMIT_WINDOW', 60), // seconds
    ],

    'circuit_breaker' => [
        'threshold' => env('SMS_CIRCUIT_BREAKER_THRESHOLD', 5),
        'timeout' => env('SMS_CIRCUIT_BREAKER_TIMEOUT', 60), // seconds
    ],

    'performance' => [
        'connection_pool_size' => env('SMS_CONNECTION_POOL_SIZE', 100),
        'concurrent_requests' => env('SMS_CONCURRENT_REQUESTS', 50),
    ],

    'logging' => [
        'enabled' => env('SMS_LOGGING_ENABLED', true),
        'channel' => env('SMS_LOGGING_CHANNEL', 'daily'),
        'slow_request_threshold' => env('SMS_SLOW_REQUEST_THRESHOLD', 1000), // milliseconds
    ],
];