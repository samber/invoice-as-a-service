<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Stripe, Mailgun, SparkPost and others. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
    |
    */

    // 'mailgun' => [
    //     'domain' => env('MAILGUN_DOMAIN'),
    //     'secret' => env('MAILGUN_SECRET'),
    // ],

    // 'ses' => [
    //     'key' => env('SES_KEY'),
    //     'secret' => env('SES_SECRET'),
    //     'region' => 'us-east-1',
    // ],

    // 'sparkpost' => [
    //     'secret' => env('SPARKPOST_SECRET'),
    // ],

    // 'stripe' => [
    //     'model' => App\User::class,
    //     'key' => env('STRIPE_KEY'),
    //     'secret' => env('STRIPE_SECRET'),
    // ],

    's3' => [
        'timeout' => (int) env('AWS_S3_TIMEOUT', 5),     // in seconds
    ],

    // Digital signature of generated PDFs. Disabled when neither certificate nor key is set.
    // Inline PEM wins over the file path.
    'pdf_sign' => [
        'cert' => env('PDF_SIGN_CERT'),
        'cert_file_path' => env('PDF_SIGN_CERT_FILE_PATH'),
        'key' => env('PDF_SIGN_KEY'),
        'key_file_path' => env('PDF_SIGN_KEY_FILE_PATH'),
        'key_passphrase' => env('PDF_SIGN_KEY_PASSPHRASE'),
    ],

];
