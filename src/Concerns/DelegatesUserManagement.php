<?php

namespace Basics13\Concerns;

use Basics13\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * The accounts an actor manages through `users.view_own` and `users.manage_own`: those sharing an
 * active type in which the actor is an admin. The actor itself, the admins of a shared type and
 * whoever manages accounts globally are left out, so a type admin can never act on a peer or on a
 * global manager. The policy, the lists, the selector and the permissions summary read it here so
 * every entry point draws the same line.
 */
trait DelegatesUserManagement
{
    /**
     * The permissions that make an account a global manager, out of reach of a type admin.
     *
     * @var list<string>
     */
    private static array $globalUserPermissions = ['users.manage', 'users.edit'];

    /**
     * Whether the actor manages accounts only as a type admin: `users.manage_own` without the
     * global `users.edit`, which `users.manage` also grants.
     */
    protected function managesAsTypeAdmin(Authenticatable $actor): bool
    {
        return method_exists($actor, 'hasPermissionTo')
            && $actor->hasPermissionTo('users.manage_own')
            && ! $actor->hasPermissionTo('users.edit');
    }

    /**
     * The active types, outside the trash, in which the actor is an admin.
     *
     * @return list<int>
     */
    protected function administeredTypeIds(Authenticatable $actor): array
    {
        return array_values(array_map(intval(...), DB::table('BAS_user_user_type')
            ->join('BAS_user_types', 'BAS_user_types.id', '=', 'BAS_user_user_type.user_type_id')
            ->where('BAS_user_user_type.user_id', $actor->getAuthIdentifier())
            ->where('BAS_user_user_type.is_admin', true)
            ->where('BAS_user_types.active', true)
            ->whereNull('BAS_user_types.deleted_at')
            ->pluck('BAS_user_user_type.user_type_id')
            ->all()));
    }

    /**
     * Narrow a query over the configured users model to the accounts the actor manages.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function whereManagedBy(Builder $query, Authenticatable $actor): Builder
    {
        $typeIds = $this->administeredTypeIds($actor);

        if ($typeIds === []) {
            return $query->whereRaw('1 = 0');
        }

        $userKey = $query->getModel()->getQualifiedKeyName();

        return $query
            ->whereKeyNot($actor->getAuthIdentifier())
            ->where('is_super_admin', false)
            ->whereExists(static fn (QueryBuilder $types): QueryBuilder => $types
                ->from('BAS_user_user_type')
                ->whereColumn('BAS_user_user_type.user_id', $userKey)
                ->whereIn('BAS_user_user_type.user_type_id', $typeIds))
            ->whereNotExists(static fn (QueryBuilder $admins): QueryBuilder => $admins
                ->from('BAS_user_user_type')
                ->whereColumn('BAS_user_user_type.user_id', $userKey)
                ->whereIn('BAS_user_user_type.user_type_id', $typeIds)
                ->where('BAS_user_user_type.is_admin', true))
            ->whereNotExists(static fn (QueryBuilder $managers): QueryBuilder => $managers
                ->from('BAS_role_user')
                ->join('BAS_roles', 'BAS_roles.id', '=', 'BAS_role_user.role_id')
                ->join('BAS_permission_role', 'BAS_permission_role.role_id', '=', 'BAS_roles.id')
                ->join('BAS_permissions', 'BAS_permissions.id', '=', 'BAS_permission_role.permission_id')
                ->whereColumn('BAS_role_user.user_id', $userKey)
                ->where('BAS_roles.active', true)
                ->whereNull('BAS_roles.deleted_at')
                ->whereIn('BAS_permissions.slug', self::$globalUserPermissions));
    }

    /**
     * Whether the actor manages this account, trashed or not.
     */
    protected function manages(Authenticatable $actor, Model $record): bool
    {
        return $this->whereManagedBy($record->newQueryWithoutScopes()->whereKey($record->getKey()), $actor)->exists();
    }

    /**
     * The roles a type admin may hand out: those whose every permission the actor holds itself, so
     * a delegated assignment never grants more than the actor has.
     *
     * @return list<int>
     */
    protected function grantableRoleIds(Authenticatable $actor): array
    {
        if (! method_exists($actor, 'hasPermissionTo')) {
            return [];
        }

        return array_values(Role::query()
            ->where('is_system', false)
            ->with('permissions')
            ->get(['id'])
            ->filter(static fn (Role $role): bool => $role->permissions
                ->every(static fn (Model $permission): bool => $actor->hasPermissionTo((string) $permission->getAttribute('slug'))))
            ->map(static fn (Role $role): int => $role->id)
            ->all());
    }
}
