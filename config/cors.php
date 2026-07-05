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

    // false tant que l'auth reste par Bearer token (pas de cookie cross-site à
    // transmettre). À repasser à true seulement si migration vers Sanctum SPA
    // (cookie HttpOnly) — jamais combiné avec allowed_origins = '*'.
    'supports_credentials' => false,

];
