<?php

namespace Basics13\Actions\Fortify;

use LogicException;
use Basics13\Models\Role;
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

        $this->assignRegistrationRole($user);

        return $user;
    }

    /**
     * Gives the account the role configured for registrations, if any. A configured slug that names
     * no role outside the trash, or a system role, is a misconfiguration and fails loudly instead
     * of creating an account with less, or more, access than intended.
     */
    private function assignRegistrationRole(AuthenticatableUser $user): void
    {
        $slug = config('basics13.roles.registration_role');

        if ($slug === null) {
            return;
        }

        if (! method_exists($user, 'assignRole')) {
            throw new LogicException('The configured users model must use the Basics13\\Concerns\\HasRoles concern.');
        }

        $role = Role::query()
            ->where('slug', $slug)
            ->where('is_system', false)
            ->first() ?? throw new LogicException("The registration role [{$slug}] does not exist or is a system role.");

        $user->assignRole($role);
    }
}
