<?php

namespace Basics13\Policies;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as User;

/**
 * Account management abilities. Accounts are not tied to a role, so every verified user can
 * administer them, exactly like the domain resources.
 *
 * The record arrives as the configured users model rather than a concrete class, because the host
 * may replace it (`auth.providers.users.model`). The policy is registered for that configured model
 * in the service provider, not bound by a `#[UsePolicy]` attribute.
 */
class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Model $record): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Model $record): bool
    {
        return true;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Model $record): bool
    {
        return true;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Model $record): bool
    {
        return true;
    }

    /**
     * Determine whether the user can archive the model.
     */
    public function archive(User $user, Model $record): bool
    {
        return true;
    }

    /**
     * Determine whether the user can activate the model.
     */
    public function activate(User $user, Model $record): bool
    {
        return true;
    }
}
