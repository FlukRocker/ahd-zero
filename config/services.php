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

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google_analytics' => [
        // GA4 measurement ID. Default is the prod ahd-zero property — set
        // GA_MEASUREMENT_ID=null in local/staging .env to disable tracking.
        'measurement_id' => env('GA_MEASUREMENT_ID', 'G-GQM12V5ZE5'),
    ],

    'turnstile' => [
        // Cloudflare Turnstile keys for member auth + comment forms.
        // Both null = widget skipped + verification bypassed (local dev).
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

    'akuma_player' => [
        'url' => env('AKUMA_PLAYER_URL', 'http://65.108.61.69:3002'),
        // Default token kept inline so the API can resolve Drive IDs without
        // requiring an env var on every host (matches kurokami's behavior).
        'token' => env('AKUMA_PLAYER_TOKEN', '23xSO2aBkri5yY35sjA9'),
        'player_domain' => env('AKUMA_PLAYER_DOMAIN', 'https://akuma-player.xyz'),
        'ads_embed_url' => env('AKUMA_PLAYER_ADS_EMBED', 'https://anime-hdzero.com/player/embed.php'),
    ],

];
