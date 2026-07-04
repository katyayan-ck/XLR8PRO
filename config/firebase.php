<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase Configuration
    |--------------------------------------------------------------------------
    |
    | Firebase service configuration for Cloud Messaging integration
    |
    */

    'enabled' => env('FIREBASE_ENABLED', true),

    'project_id' => env('FIREBASE_PROJECT_ID'),

    'credentials' => [
        'type' => env('FIREBASE_TYPE', 'service_account'),
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'private_key_id' => env('FIREBASE_PRIVATE_KEY_ID'),
        'private_key' => env('FIREBASE_PRIVATE_KEY'),
        'client_email' => env('FIREBASE_CLIENT_EMAIL'),
        'client_id' => env('FIREBASE_CLIENT_ID'),
        'auth_uri' => env('FIREBASE_AUTH_URI', 'https://accounts.google.com/o/oauth2/auth'),
        'token_uri' => env('FIREBASE_TOKEN_URI', 'https://oauth2.googleapis.com/token'),
        'auth_provider_x509_cert_url' => env('FIREBASE_AUTH_PROVIDER_X509_CERT_URL', 'https://www.googleapis.com/oauth2/v1/certs'),
        'client_x509_cert_url' => env('FIREBASE_CLIENT_X509_CERT_URL'),
    ],

    'app' => [
        'name' => env('FIREBASE_APP_NAME', 'VDMS'),
        'package_name' => env('FIREBASE_PACKAGE_NAME', 'com.insighttech.vdms'),
        'ios_bundle_id' => env('FIREBASE_IOS_BUNDLE_ID', 'com.insighttech.vdms'),
    ],

    'timeout' => env('FIREBASE_TIMEOUT', 30),

    'retry_attempts' => env('FIREBASE_RETRY_ATTEMPTS', 3),

    'log_requests' => env('FIREBASE_LOG_REQUESTS', true),
];
