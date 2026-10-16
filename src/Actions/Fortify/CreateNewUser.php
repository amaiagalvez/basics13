<?php

namespace Basics13\Actions\Fortify;

use LogicException;
use Illuminate\Support\Facades\Validator;
use Basics13\Concerns\ProfileValidationRules;
use Basics13\Concerns\PasswordValidationRules;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): AuthenticatableUser
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $userModel = config('auth.providers.users.model');

        if (! is_string($userModel) || ! is_subclass_of($userModel, AuthenticatableUser::class)) {
            throw new LogicException('The configured users model must extend the Laravel authentication user model.');
        }

        $user = new $userModel;
        $user->fill([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
        $user->save();

        return $user;
    }
}
