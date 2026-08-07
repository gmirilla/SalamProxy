<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Proxy Shared Secret
    |--------------------------------------------------------------------------
    |
    | Must match PROXY_SECRET in the calling app's .env. Read through config()
    | rather than env() directly, so it keeps working after config:cache.
    |
    */

    'secret' => env('PROXY_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | eCMR (NPF) Credentials
    |--------------------------------------------------------------------------
    */

    'ecmr' => [
        'url'      => env('eMCR_URL'),
        'username' => env('eMCR_USERNAME'),
        'password' => env('eMCR_PASSWORD'),
    ],

];
