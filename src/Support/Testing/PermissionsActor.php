<?php

namespace Basics13\Support\Testing;

use Illuminate\Foundation\Auth\User;

/**
 * An authenticated user answering permission checks from a fixed list, the way the HasRoles concern
 * does once its slugs are loaded, so policies and Form Requests can be unit tested without a
 * database. `{module}.manage` grants every action of its module, as it does for a real account. It
 * is never saved: the key is set by hand.
 */
final class PermissionsActor extends User
{
    /** @var list<string> */
    private array $permissions = [];

    private bool $superAdmin = false;

    /**
     * @param  list<string>  $permissions
     */
    public static function holding(array $permissions = [], int $id = 1): self
    {
        $actor = new self;
        $actor->permissions = $permissions;
        $actor->setAttribute('id', $id);

        return $actor;
    }

    public static function superAdmin(int $id = 1): self
    {
        $actor = self::holding([], $id);
        $actor->superAdmin = true;

        return $actor;
    }

    public function isSuperAdmin(): bool
    {
        return $this->superAdmin;
    }

    /**
     * Holding a permission means holding a role: an actor without any permission is an account
     * without roles, which the gate refuses everything.
     */
    public function holdsAnyRole(): bool
    {
        return $this->superAdmin || $this->permissions !== [];
    }

    public function hasPermissionTo(string $permission): bool
    {
        $module = explode('.', $permission, 2)[0];

        return $this->superAdmin
            || in_array($permission, $this->permissions, true)
            || in_array($module.'.manage', $this->permissions, true);
    }
}
