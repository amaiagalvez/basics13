<?php

namespace Basics13\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * How a policy asks for a permission. The actor arrives as the bare contract, while the permission
 * check lives in the HasRoles concern every configured users model uses; an actor without it holds
 * nothing.
 */
trait ChecksPermissions
{
    protected function permits(Authenticatable $user, string $permission): bool
    {
        return method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo($permission);
    }

    protected function isSuperAdmin(Authenticatable $user): bool
    {
        return method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
    }
}
