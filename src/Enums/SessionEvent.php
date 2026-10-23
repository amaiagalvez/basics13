<?php

namespace Basics13\Enums;

/**
 * What a `BAS_sessions` row records: a successful login, a failed attempt or the lockout a
 * login reaches. The logout is not an event: it is stamped on the row of its login.
 */
enum SessionEvent: string
{
    case Login = 'login';
    case Failed = 'failed';
    case Lockout = 'lockout';

    /**
     * The translated result as the session list shows it.
     */
    public function label(): string
    {
        return match ($this) {
            self::Login => __('Successful sign-in'),
            self::Failed => __('Failed sign-in'),
            self::Lockout => __('Sign-in locked'),
        };
    }
}
