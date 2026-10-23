<?php

/*
|--------------------------------------------------------------------------
| Delivery
|--------------------------------------------------------------------------
|
| Instance-wide delivery destinations for generated invoices. Values are raw
| env strings: App\Helpers\Delivery casts and validates them per request.
| Blank values mean "not configured". Request body fields win over these
| defaults unless per_request_enabled is false.
|
*/

// Unset or empty keeps the default (enabled). Any other value that is not a recognised
// truthy word disables per-request delivery: a typo must lock the instance, not open it.
$perRequestEnabled = env('DELIVERY_PER_REQUEST_ENABLED');

return [

    // When false, `s3`, `ftp`, `webhook`, `zapier` and `email` in the request body are rejected (422)
    // and only the DELIVERY_* destinations below run.
    'per_request_enabled' => $perRequestEnabled === null || $perRequestEnabled === ''
        ? true
        : filter_var($perRequestEnabled, FILTER_VALIDATE_BOOLEAN),

    // There is no s3 block: a presigned URL is signed for one object, so it only exists per request.

    'ftp' => [
        'host' => env('DELIVERY_FTP_HOST'),
        'port' => env('DELIVERY_FTP_PORT'),
        'ssl' => env('DELIVERY_FTP_SSL'),
        'passive' => env('DELIVERY_FTP_PASSIVE'),
        'username' => env('DELIVERY_FTP_USERNAME'),
        'password' => env('DELIVERY_FTP_PASSWORD'),
        'path' => env('DELIVERY_FTP_PATH'),
    ],

    'webhook' => [
        'url' => env('DELIVERY_WEBHOOK_URL'),
        'headers' => env('DELIVERY_WEBHOOK_HEADERS'),   // JSON object
    ],

    'zapier' => [
        'zap_url' => env('DELIVERY_ZAPIER_URL'),
        'filename' => env('DELIVERY_ZAPIER_FILENAME'),
    ],

    // SMTP transport stays in the MAIL_* variables (config/mail.php).
    'email' => [
        'to' => env('DELIVERY_EMAIL_TO'),
        'cc' => env('DELIVERY_EMAIL_CC'),               // comma-separated
        'bcc' => env('DELIVERY_EMAIL_BCC'),             // comma-separated
        'template' => env('DELIVERY_EMAIL_TEMPLATE'),
    ],

];
