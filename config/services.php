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

    // Connexion facultative via Portier (OpenID Connect), en plus du mot de passe.
    'portier' => [
        'actif' => (bool) env('PORTIER_ACTIF', false),
        'issuer' => env('PORTIER_ISSUER', 'https://portier.mawena.cloud'),
        'client_id' => env('PORTIER_CLIENT_ID', 'pulse'),
        'client_secret' => env('PORTIER_CLIENT_SECRET'),
        // Vide : APP_URL + /auth/portier/retour
        'redirect' => env('PORTIER_REDIRECT_URI'),
    ],

];
