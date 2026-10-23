<?php

namespace Basics13\Tests\Feature\Roles;

use Basics13\Models\Role;
use Basics13\Models\Permission;
use Basics13\Tests\Feature\FeatureTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SeedPermissionsTest extends FeatureTestCase
{
    use RefreshDatabase;

    public function test_it_seeds_every_action_of_the_modules_and_grants_admin_idempotently(): void
    {
        seedPermissions(['reports'], ['reports.manage']);
        seedPermissions(['reports'], ['reports.manage']);

        $this->assertSame(
            array_map(static fn (string $action): string => 'reports.'.$action, Permission::ACTIONS),
            Permission::query()->where('module', 'reports')->orderBy('id')->pluck('slug')->all(),
        );
        $admin = Role::query()->where('slug', 'admin')->firstOrFail();
        $this->assertSame(1, $admin->permissions()->where('slug', 'reports.manage')->count());
        $this->assertSame(0, $admin->permissions()->where('slug', 'reports.view')->count());
    }

    public function test_it_only_seeds_the_permissions_when_there_is_no_admin_role(): void
    {
        // A system role refuses the model delete, so it is trashed underneath.
        Role::query()->where('slug', 'admin')->toBase()->update(['deleted_at' => now()]);

        seedPermissions(['reports'], ['reports.manage']);

        $this->assertSame(count(Permission::ACTIONS), Permission::query()->where('module', 'reports')->count());
        $this->assertSame(0, Role::withTrashed()->where('slug', 'admin')->firstOrFail()->permissions()->where('slug', 'reports.manage')->count());
    }
}
