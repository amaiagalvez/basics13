<?php

namespace Basics13\Queries\Permissions;

use Basics13\Models\Permission;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * The seeded permissions, grouped by module. They are few and read-only, so they are listed whole
 * rather than paginated.
 */
final class PermissionListQuery
{
    /**
     * @return Collection<array-key, Collection<int, Permission>>
     */
    public function byModule(): Collection
    {
        return Permission::query()
            ->orderBy('module')
            ->orderBy('id')
            ->get()
            ->toBase()
            ->groupBy('module');
    }

    /**
     * The same groups, each permission with the active roles that grant it, the way the permission
     * list shows them.
     *
     * @return Collection<array-key, Collection<int, Permission>>
     */
    public function byModuleWithRoles(): Collection
    {
        return Permission::query()
            ->with(['roles' => static fn (BelongsToMany $roles): BelongsToMany => $roles
                ->where('roles.active', true)
                ->orderBy('roles.name')])
            ->orderBy('module')
            ->orderBy('id')
            ->get()
            ->toBase()
            ->groupBy('module');
    }
}
