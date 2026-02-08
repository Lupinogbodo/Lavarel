<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | SECURITY: Properly configured CORS prevents unauthorized cross-origin requests
    | and protects against CSRF attacks in API contexts.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', '*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    /*
     * SECURITY: Only allow specific trusted origins in production
     * Never use '*' in production - it defeats CORS protection
     */
    'allowed_origins' => env('APP_ENV') === 'local' 
        ? ['*'] 
        : explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://localhost:8000')),

    'allowed_origins_patterns' => [],

    /*
     * SECURITY: Only allow necessary headers
     * Don't use '*' - explicitly list required headers
     */
    'allowed_headers' => [
        'Content-Type',
        'X-Requested-With',
        'Authorization',
        'Accept',
        'Origin',
        'X-CSRF-Token',
    ],

    /*
     * SECURITY: Only expose necessary headers to JavaScript
     */
    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'X-RateLimit-Reset',
        'X-Token-Rotated',
    ],

    'max_age' => 3600,

    /*
     * SECURITY: Set to true to allow credentials (cookies, authorization headers)
     * Only works with specific origins, not '*'
     */
    'supports_credentials' => env('APP_ENV') !== 'local',

];
