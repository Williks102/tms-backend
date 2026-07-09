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

    // Passerelle de paiement en ligne (achat de billet, /billets) — voir
    // App\Services\Payments\PaiementProService (SOAP, initTransact). Pas
    // d'hôte sandbox distinct pour ce service — www.paiementpro.net sert à
    // la fois le test et le réel, le mode dépend du merchantId lui-même.
    'paiementpro' => [
        'merchant_id'  => env('PAIEMENTPRO_MERCHANT_ID'),
        'base_url'     => env('PAIEMENTPRO_BASE_URL', 'https://www.paiementpro.net'),
        'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),
    ],

];
