<?php

namespace Basics13\Policies;

use Basics13\Models\UserType;
use Basics13\Concerns\ChecksPermissions;
use Illuminate\Contracts\Auth\Authenticatable as User;

/**
 * User type abilities, each one granted by its `user_types.{action}` permission.
 */
class UserTypePolicy
{
    use ChecksPermissions;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->permits($user, 'user_types.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->permits($user, 'user_types.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, UserType $userType): bool
    {
        return $this->permits($user, 'user_types.edit');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, UserType $userType): bool
    {
        return $this->permits($user, 'user_types.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, UserType $userType): bool
    {
        return $this->permits($user, 'user_types.restore');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, UserType $userType): bool
    {
        return $this->permits($user, 'user_types.force_delete');
    }

    /**
     * Determine whether the user can archive the model.
     */
    public function archive(User $user, UserType $userType): bool
    {
        return $this->permits($user, 'user_types.archive');
    }

    /**
     * Determine whether the user can activate the model.
     */
    public function activate(User $user, UserType $userType): bool
    {
        return $this->permits($user, 'user_types.archive');
    }
}
