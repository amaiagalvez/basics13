<?php

namespace Basics13\Concerns;

use LogicException;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves the Eloquent model that backs user accounts.
 *
 * The package never hardcodes `Basics13\Models\User`: a host may replace it through
 * `auth.providers.users.model`, exactly like the Fortify actions and the validation traits do. The
 * guard lives here so every class that reads the config fails the same way and with one message.
 */
trait ResolvesUserModel
{
    /**
     * @return class-string<Model>
     */
    protected function userModelClass(): string
    {
        $userModel = config('auth.providers.users.model');

        if (! is_string($userModel) || ! is_subclass_of($userModel, Model::class)) {
            throw new LogicException('The configured users model must be an Eloquent model.');
        }

        return $userModel;
    }
}
