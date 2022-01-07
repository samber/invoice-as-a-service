<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => getenv('APP_LOG') ? getenv('APP_LOG') : 'errorlog',

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "custom", "stack"
    |
    */

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single'],
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => getenv('LOG_LEVEL') ? getenv('LOG_LEVEL') : 'debug',
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => getenv('LOG_LEVEL') ? getenv('LOG_LEVEL') : 'debug',
            'days' => 7,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Laravel Log',
            'emoji' => ':boom:',
            'level' => getenv('LOG_LEVEL') ? getenv('LOG_LEVEL') : 'debug',
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => getenv('LOG_LEVEL') ? getenv('LOG_LEVEL') : 'debug',
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => getenv('LOG_LEVEL') ? getenv('LOG_LEVEL') : 'debug',
        ],
    ],

];
