<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | Root of the SMS gateway API, e.g. https://smsgate.test/api/v1
    |
    */

    'base_url' => env('AZELYA_SMS_BASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Credentials
    |--------------------------------------------------------------------------
    |
    | When no access token is cached, the client logs in automatically with
    | these credentials and caches the token. Configure either the user pair
    | or the app pair (or both — the app pair takes precedence).
    |
    */

    'email' => env('AZELYA_SMS_EMAIL'),
    'password' => env('AZELYA_SMS_PASSWORD'),

    'app_email' => env('AZELYA_SMS_APP_EMAIL'),
    'app_password' => env('AZELYA_SMS_APP_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Pre-provisioned access token
    |--------------------------------------------------------------------------
    |
    | Skips auto-login entirely and always sends this token.
    |
    */

    'token' => env('AZELYA_SMS_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Gateway device token
    |--------------------------------------------------------------------------
    |
    | Static device token for the gateway device endpoints (deviceGateway()).
    | Sent as the X-Device-Token header.
    |
    */

    'device_token' => env('AZELYA_SMS_DEVICE_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | HTTP behaviour
    |--------------------------------------------------------------------------
    */

    'timeout' => (float) env('AZELYA_SMS_TIMEOUT', 30),
    'connect_timeout' => (float) env('AZELYA_SMS_CONNECT_TIMEOUT', 10),
    'verify' => filter_var(env('AZELYA_SMS_VERIFY', true), FILTER_VALIDATE_BOOL),

    /*
    | When a bearer request fails with 401 and credentials are configured,
    | re-login once and retry the request. Safe because a 401 means the
    | token was rejected before any controller ran.
    */

    'retry_on_unauthorized' => filter_var(env('AZELYA_SMS_RETRY_ON_UNAUTHORIZED', true), FILTER_VALIDATE_BOOL),

    /*
    |--------------------------------------------------------------------------
    | Token cache
    |--------------------------------------------------------------------------
    |
    | TTL in seconds for tokens cached in the Laravel cache repository
    | (null = store forever). Stale tokens are recovered from automatically
    | via the re-login flow, so this only exists for cache hygiene.
    |
    */

    'token_ttl' => env('AZELYA_SMS_TOKEN_TTL'),

];
