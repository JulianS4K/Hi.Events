<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'stripe' => [
        'secret_key' => env('STRIPE_SECRET_KEY'),
        'public_key' => env('STRIPE_PUBLIC_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),

        // Canadian platform (Optional)
        'ca_secret_key' => env('STRIPE_CA_SECRET_KEY', env('STRIPE_SECRET_KEY')),
        'ca_public_key' => env('STRIPE_CA_PUBLIC_KEY', env('STRIPE_PUBLIC_KEY')),
        'ca_webhook_secret' => env('STRIPE_CA_WEBHOOK_SECRET', env('STRIPE_WEBHOOK_SECRET')),

        // Irish platform (Optional)
        'ie_secret_key' => env('STRIPE_IE_SECRET_KEY', env('STRIPE_SECRET_KEY')),
        'ie_public_key' => env('STRIPE_IE_PUBLIC_KEY', env('STRIPE_PUBLIC_KEY')),
        'ie_webhook_secret' => env('STRIPE_IE_WEBHOOK_SECRET', env('STRIPE_WEBHOOK_SECRET')),

        // Primary platform for new organizers
        'primary_platform' => env('STRIPE_PRIMARY_PLATFORM'),
    ],
    'open_exchange_rates' => [
        'app_id' => env('OPEN_EXCHANGE_RATES_APP_ID'),
    ],

    'apple_wallet' => [
        'enabled'       => env('APPLE_WALLET_ENABLED', false),
        'team_id'       => env('APPLE_WALLET_TEAM_ID'),
        'pass_type_id'  => env('APPLE_WALLET_PASS_TYPE_ID'),
        // PEM-encoded certificate and private key (base64 the file contents for env storage)
        'certificate'   => env('APPLE_WALLET_CERTIFICATE'),
        'private_key'   => env('APPLE_WALLET_PRIVATE_KEY'),
        'private_key_password' => env('APPLE_WALLET_PRIVATE_KEY_PASSWORD', ''),
        // Download from https://www.apple.com/certificateauthority/
        'wwdr_certificate' => env('APPLE_WALLET_WWDR_CERTIFICATE'),
    ],

    'google_wallet' => [
        'enabled'    => env('GOOGLE_WALLET_ENABLED', false),
        'issuer_id'  => env('GOOGLE_WALLET_ISSUER_ID'),
        // Full JSON contents of your Google Cloud service account key file
        'service_account_json' => env('GOOGLE_WALLET_SERVICE_ACCOUNT_JSON'),
    ],
];
// Appending wallet config — will be added inside the return array manually
