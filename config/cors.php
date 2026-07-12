<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Origine explicite du frontend uniquement — jamais de '*' ici. Voir
    | runbook-securite-tms.md point 6.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:3000'),
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // true — migration vers Sanctum SPA (cookie HttpOnly) effectuée, voir
    // correctif.md point 4 et AuthController::login(). Obligatoire pour que
    // le navigateur transmette le cookie de session cross-origin — jamais à
    // combiner avec allowed_origins = '*' (interdit par la spec CORS de
    // toute façon dès que credentials=true, le navigateur rejette).
    'supports_credentials' => true,

];
