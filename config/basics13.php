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

    'roles' => [
        // Slug of the role a newly registered account receives. Null leaves it without roles, so it
        // can do nothing until someone with `roles.manage` gives it one. A system role is refused:
        // only the super-admin assigns those.
        'registration_role' => null,
    ],

    // TODO: hau ere db-aren config-ean erabili behar da
    'validation' => [
        'max_length' => [
            'string' => 191,
            'longtext' => 10000,
        ],
    ],
];
