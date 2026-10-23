<?php

namespace Basics13\Policies;

use Illuminate\Database\Eloquent\Model;
use Basics13\Concerns\ChecksPermissions;
use Illuminate\Contracts\Auth\Authenticatable as User;

/**
 * Account management abilities, each one granted by its `users.{action}` permission.
 *
 * The super-admin account is out of reach of everybody else: no permission lets another user edit,
 * archive, trash, restore or delete it. That rule is checked before the permission. The account
 * holder manages it from the settings, which do not go through this policy.
 *
 * The record arrives as the configured users model rather than a concrete class, because the host
 * may replace it (`auth.providers.users.model`). The policy is registered for that configured model
 * in the service provider, not bound by a `#[UsePolicy]` attribute.
 */
class UserPolicy
{
    use ChecksPermissions;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->permits($user, 'users.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->permits($user, 'users.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Model $record): bool
    {
        return $this->reachable($user, $record) && $this->permits($user, 'users.edit');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Model $record): bool
    {
        return $this->reachable($user, $record) && $this->permits($user, 'users.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Model $record): bool
    {
        return $this->reachable($user, $record) && $this->permits($user, 'users.restore');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Model $record): bool
    {
        return $this->reachable($user, $record) && $this->permits($user, 'users.force_delete');
    }

    /**
     * Determine whether the user can archive the model.
     */
    public function archive(User $user, Model $record): bool
    {
        return $this->reachable($user, $record) && $this->permits($user, 'users.archive');
    }

    /**
     * Determine whether the user can activate the model.
     */
    public function activate(User $user, Model $record): bool
    {
        return $this->reachable($user, $record) && $this->permits($user, 'users.archive');
    }

    /**
     * Whether the user may act on this account at all: the super-admin only on itself.
     */
    private function reachable(User $user, Model $record): bool
    {
        return ! (bool) $record->getAttribute('is_super_admin')
            || (int) $record->getKey() === (int) $user->getAuthIdentifier();
    }

    /**
     * Determine whether the user can impersonate the model.
     */
    public function impersonate(User $user, Model $record): bool
    {
        return $this->isSuperAdmin($user)
            && ! (bool) $record->getAttribute('is_super_admin')
            && (int) $record->getKey() !== (int) $user->getAuthIdentifier();
    }
}
