<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OTP Cache Store
    |--------------------------------------------------------------------------
    |
    | The cache store used to hold verification codes. Leave this null to use
    | the application's default store, which should be "redis" in any
    | environment running more than one application process.
    |
    */

    'store' => env('OTP_CACHE_STORE'),

    /*
    |--------------------------------------------------------------------------
    | Code Format & Lifetime
    |--------------------------------------------------------------------------
    |
    | "ttl" and "resend_cooldown" are expressed in seconds. A code is discarded
    | once "max_attempts" incorrect submissions have been made against it, so
    | a six digit code cannot be brute forced within its lifetime.
    |
    */

    'length' => (int) env('OTP_LENGTH', 6),

    'ttl' => (int) env('OTP_TTL', 300),

    'resend_cooldown' => (int) env('OTP_RESEND_COOLDOWN', 60),

    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),

    /*
    |--------------------------------------------------------------------------
    | Code Exposure
    |--------------------------------------------------------------------------
    |
    | Codes are hashed before they are cached, so an issued code cannot be read
    | back. While this is enabled the plain code is mirrored under a second key
    | for the same lifetime, which is what "/api/msisdn/code" returns so a
    | client without an SMS provider can still complete the flow.
    |
    | This hands out a credential. It is ignored in production regardless of
    | the value here, and should stay off anywhere reachable by real users.
    |
    */

    'expose_codes' => (bool) env('OTP_EXPOSE_CODES', env('APP_ENV') !== 'production'),

];
