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
}
