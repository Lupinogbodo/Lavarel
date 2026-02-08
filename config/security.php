<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Custom security settings for the application
    |
    */

    /*
     * Password Requirements
     */
    'password' => [
        'min_length' => 12,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_numbers' => true,
        'require_special_chars' => true,
        'expire_days' => 90, // Force password change after 90 days
    ],

    /*
     * Session Security
     */
    'session' => [
        'max_concurrent' => 3, // Maximum concurrent sessions per user
        'idle_timeout' => 1800, // 30 minutes idle timeout
        'absolute_timeout' => 28800, // 8 hours absolute timeout
    ],

    /*
     * Rate Limiting Tiers
     */
    'rate_limits' => [
        'api' => [
            'guest' => '60,1',      // 60 requests per minute for guests
            'authenticated' => '120,1', // 120 requests per minute for authenticated users
            'premium' => '300,1',   // 300 requests per minute for premium users
        ],
        'auth' => [
            'login' => '5,1',       // 5 login attempts per minute
            'register' => '3,1',    // 3 registration attempts per minute
            'password_reset' => '3,60', // 3 password reset requests per hour
        ],
    ],

    /*
     * Token Security
     */
    'tokens' => [
        'lifetime' => 7,            // Token lifetime in days
        'rotation_threshold' => 7,  // Rotate tokens after X days
        'fingerprint_strict' => true, // Strict fingerprint validation
        'blacklist_ttl' => 30,      // Keep blacklisted tokens for 30 days
    ],

    /*
     * Account Lockout
     */
    'lockout' => [
        'max_attempts' => 10,       // Lock account after 10 failed logins
        'duration' => 1800,         // Lock duration in seconds (30 minutes)
        'notify_user' => true,      // Send email on account lockout
    ],

    /*
     * Input Validation
     */
    'validation' => [
        'max_input_vars' => 1000,   // Maximum input variables
        'max_json_depth' => 10,     // Maximum JSON nesting depth
        'max_file_size' => 10240,   // Maximum file upload size (KB)
    ],

    /*
     * Audit Logging
     */
    'audit' => [
        'enabled' => true,
        'events' => [
            'login',
            'logout',
            'password_change',
            'email_change',
            'permission_change',
            'data_export',
            'data_deletion',
        ],
        'retention_days' => 365,    // Keep audit logs for 1 year
    ],

    /*
     * Security Headers (default values for SecurityHeaders middleware)
     */
    'headers' => [
        'hsts_max_age' => 31536000,         // 1 year
        'csp_report_uri' => env('CSP_REPORT_URI', null),
        'report_only' => env('CSP_REPORT_ONLY', false),
    ],

    /*
     * IP Whitelist/Blacklist
     */
    'ip_filtering' => [
        'enabled' => env('IP_FILTERING_ENABLED', false),
        'whitelist' => explode(',', env('IP_WHITELIST', '')),
        'blacklist' => explode(',', env('IP_BLACKLIST', '')),
    ],

    /*
     * API Security
     */
    'api' => [
        'require_https' => env('API_REQUIRE_HTTPS', true),
        'api_key_header' => 'X-API-Key',
        'api_key_rotation_days' => 90,
    ],

];
