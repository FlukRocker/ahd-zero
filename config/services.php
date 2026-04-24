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

    'akuma_player' => [
        'url' => env('AKUMA_PLAYER_URL', 'http://65.108.61.69:3002'),
        'token' => env('AKUMA_PLAYER_TOKEN'),
        'player_domain' => env('AKUMA_PLAYER_DOMAIN', 'https://akuma-player.xyz'),
        'ads_embed_url' => env('AKUMA_PLAYER_ADS_EMBED', 'https://anime-hdzero.com/player/embed.php'),
    ],

];
