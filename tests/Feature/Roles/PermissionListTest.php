<?php

namespace Basics13\Tests\Feature\Roles;

use Basics13\Models\User;
use Basics13\Models\Permission;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PermissionListTest extends RoleTestCase
{
    use RefreshDatabase;

    public function test_the_permissions_are_listed_by_module_with_the_roles_that_grant_them(): void
    {
        $this->actingAs(User::factory()->withPermissions(['roles.view'])->create());

        $this->get(route('permissions.index'))
            ->assertOk()
            ->assertSee('data-test="permission-module-users"', false)
            ->assertSee('data-test="permission-module-roles"', false)
            ->assertSee('data-test="permission-row-users.force_delete"', false)
            ->assertSeeText(Permission::moduleLabel('users'))
            ->assertSeeInOrder(['data-test="permission-row-users.manage"', 'Admin'], false);
    }

    public function test_the_list_needs_roles_view(): void
    {
        $this->actingAs(User::factory()->withPermissions(['users.manage'])->create());

        $this->get(route('permissions.index'))->assertForbidden();
    }

    public function test_permissions_have_no_write_routes(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(static fn ($route): bool => str_starts_with($route->uri(), 'permissions'));

        $this->assertSame(['permissions'], $routes->map->uri()->values()->all());
        $this->assertSame(['GET', 'HEAD'], $routes->first()->methods());
    }
}
