<?php

return [

    'auth' => [
        'features' => [
            'registration' => true,
            'email_verification' => true,
            'two_factor_authentication' => true,
            'passkeys' => true,
            'password_confirmation' => true,
        ],
        'passkeys' => [
            'relying_party_id' => parse_url(config('app.url'), PHP_URL_HOST) ?: null,
            'allowed_origins' => [config('app.url')],
            'user_handle_secret' => env('PASSKEYS_USER_HANDLE_SECRET', config('app.key')),
            'timeout' => 60000,
        ],
    ],

    // TODO: hau ere db-aren config-ean erabili behar da
    'validation' => [
        'max_length' => [
            'string' => 191,
            'longtext' => 10000,
        ],
    ],
];
