<?php

namespace Basics13\Enums;

/**
 * Why a login attempt failed. Only set on `failed` rows: the login does not exist, the password
 * is wrong, the account is inactive or the login is locked out. Never the password itself.
 */
enum LoginFailureReason: string
{
    case UnknownUser = 'unknown_user';
    case WrongPassword = 'wrong_password';
    case Inactive = 'inactive';
    case Locked = 'locked';

    /**
     * The translated reason as the session list shows it.
     */
    public function label(): string
    {
        return match ($this) {
            self::UnknownUser => __('Unknown login'),
            self::WrongPassword => __('Wrong password'),
            self::Inactive => __('Inactive account'),
            self::Locked => __('Rejected while locked'),
        };
    }
}
