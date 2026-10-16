<?php

namespace Basics13\Actions\Fortify;

use LogicException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Basics13\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(Authenticatable $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        if (! $user instanceof Model) {
            throw new LogicException('The configured users model must be an Eloquent model.');
        }

        $user->forceFill([
            'password' => $input['password'],
        ])->save();
    }
}
