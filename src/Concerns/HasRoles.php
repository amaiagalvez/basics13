<?php

namespace Basics13\Concerns;

use LogicException;
use Basics13\Models\Role;
use Basics13\Models\UserType;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Roles and permissions of an account. Every users model the host configures MUST use it: account
 * and domain policies ask it whether the user holds a permission.
 *
 * The slugs the account holds are read once, with a single query, into the instance, and every
 * later check is answered in memory: `{module}.manage` grants every action of its module. Only
 * active roles outside the trash count. The super-admin holds every permission without a query and
 * never receives a role.
 *
 * @phpstan-require-extends Model
 */
trait HasRoles
{
    /**
     * The permission slugs of the account's active roles, once loaded.
     *
     * @var list<string>|null
     */
    private ?array $permissionSlugs = null;

    /**
     * Whether the account holds at least one active role outside the trash, once checked.
     */
    private ?bool $holdsRole = null;

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'BAS_role_user', 'user_id', 'role_id')
            ->withPivot(['assigned_by', 'created_at']);
    }

    /**
     * The types the account belongs to, and in which of them it is an admin. They live here rather
     * than on the default model so a host that replaces it keeps them, like the roles.
     *
     * @return BelongsToMany<UserType, $this>
     */
    public function userTypes(): BelongsToMany
    {
        return $this->belongsToMany(UserType::class, 'BAS_user_user_type', 'user_id', 'user_type_id')
            ->withPivot('is_admin')
            ->withTimestamps();
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->getAttribute('is_super_admin');
    }

    public function hasPermissionTo(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $slugs = $this->permissionSlugs ??= $this->loadPermissionSlugs();
        $module = explode('.', $permission, 2)[0];

        return in_array($permission, $slugs, true) || in_array($module.'.manage', $slugs, true);
    }

    /**
     * Whether the account holds an active role outside the trash. An account without one can do
     * nothing in the application: the gate refuses every ability to it.
     */
    public function holdsAnyRole(): bool
    {
        return $this->holdsRole ??= $this->exists && $this->roles()
            ->where('BAS_roles.active', true)
            ->whereNull('BAS_roles.deleted_at')
            ->exists();
    }

    /**
     * Drop the loaded slugs, so the next check reads the roles again. Changing the roles through
     * this concern already does it; a change made any other way within the same request needs it.
     */
    public function forgetPermissions(): void
    {
        $this->permissionSlugs = null;
        $this->holdsRole = null;
    }

    public function assignRole(Role $role, ?int $assignedBy = null): void
    {
        $this->syncRoles(
            array_values(array_map(intval(...), [...$this->roles()->pluck('BAS_roles.id')->all(), $role->id])),
            $assignedBy,
        );
    }

    public function removeRole(Role $role): void
    {
        $this->roles()->detach($role->id);
        $this->forgetPermissions();
    }

    /**
     * Leave the account with exactly these roles. The ones it already holds keep who assigned them
     * and when; only the new ones are stamped with `$assignedBy` and the current time.
     *
     * @param  list<int>  $roleIds
     */
    public function syncRoles(array $roleIds, ?int $assignedBy = null): void
    {
        if ($this->isSuperAdmin() && $roleIds !== []) {
            throw new LogicException('The super-admin holds every permission and never receives a role.');
        }

        $current = array_map(intval(...), $this->roles()->pluck('BAS_roles.id')->all());
        $wanted = array_values(array_unique(array_map(intval(...), $roleIds)));

        $this->roles()->detach(array_values(array_diff($current, $wanted)));
        $this->roles()->attach(
            array_values(array_diff($wanted, $current)),
            ['assigned_by' => $assignedBy, 'created_at' => now()],
        );

        $this->forgetPermissions();
    }

    /**
     * @return list<string>
     */
    private function loadPermissionSlugs(): array
    {
        // An account not saved yet cannot hold a role.
        if (! $this->exists) {
            return [];
        }

        /** @var list<string> */
        return DB::table('BAS_permissions')
            ->join('BAS_permission_role', 'BAS_permission_role.permission_id', '=', 'BAS_permissions.id')
            ->join('BAS_roles', 'BAS_roles.id', '=', 'BAS_permission_role.role_id')
            ->join('BAS_role_user', 'BAS_role_user.role_id', '=', 'BAS_roles.id')
            ->where('BAS_role_user.user_id', $this->getKey())
            ->where('BAS_roles.active', true)
            ->whereNull('BAS_roles.deleted_at')
            ->distinct()
            ->pluck('BAS_permissions.slug')
            ->all();
    }
}
