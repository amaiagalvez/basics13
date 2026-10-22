<?php

namespace Basics13\Policies;

use Basics13\Models\Role;
use Basics13\Concerns\ChecksPermissions;
use Illuminate\Contracts\Auth\Authenticatable as User;

/**
 * Role abilities, each one granted by its `roles.{action}` permission. A system role is checked
 * first: only the super-admin edits it, and nobody archives, trashes or deletes it. A role that an
 * account holds is kept out of the trash by the model's own delete guard.
 */
class RolePolicy
{
    use ChecksPermissions;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->permits($user, 'roles.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->permits($user, 'roles.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Role $role): bool
    {
        if ($role->is_system) {
            return $this->isSuperAdmin($user);
        }

        return $this->permits($user, 'roles.edit');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Role $role): bool
    {
        return ! $role->is_system && $this->permits($user, 'roles.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Role $role): bool
    {
        return ! $role->is_system && $this->permits($user, 'roles.restore');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Role $role): bool
    {
        return ! $role->is_system && $this->permits($user, 'roles.force_delete');
    }

    /**
     * Determine whether the user can archive the model.
     */
    public function archive(User $user, Role $role): bool
    {
        return ! $role->is_system && $this->permits($user, 'roles.archive');
    }

    /**
     * Determine whether the user can activate the model.
     */
    public function activate(User $user, Role $role): bool
    {
        return ! $role->is_system && $this->permits($user, 'roles.archive');
    }
}
