<?php

namespace Basics13\Tests\Feature\Roles;

use Basics13\Models\Role;
use Basics13\Models\User;
use Basics13\Models\Permission;
use Illuminate\Support\Facades\DB;
use Basics13\Tests\Feature\FeatureTestCase;
use Basics13\Database\Seeders\BasicsAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RolePermissionSeedTest extends FeatureTestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_user_and_role_permissions_and_the_three_starting_roles(): void
    {
        // 18 from the first seed plus `users.view_own` and `users.manage_own` of the user types.
        $this->assertSame(20, Permission::query()->whereIn('module', ['roles', 'users'])->count());
        // The session log, the configuration, the user types and the email seed their own modules later.
        $this->assertSame(['email', 'roles', 'sessions', 'settings', 'user_types', 'users'], Permission::query()->distinct()->orderBy('module')->pluck('module')->all());

        $this->assertEqualsCanonicalizing(['users.manage', 'roles.manage', 'sessions.manage', 'settings.manage', 'user_types.manage', 'email.manage'], $this->permissionsOf('admin'));
        $this->assertEqualsCanonicalizing(['users.view', 'users.create', 'users.edit', 'roles.view'], $this->permissionsOf('manager'));
        $this->assertEqualsCanonicalizing(['users.view', 'roles.view'], $this->permissionsOf('viewer'));

        $this->assertTrue(Role::query()->where('slug', 'admin')->value('is_system'));
        $this->assertSame(0, Role::query()->where('slug', '!=', 'admin')->where('is_system', true)->count());
    }

    public function test_seeding_again_duplicates_nothing_and_keeps_the_permissions_roles_were_given(): void
    {
        User::factory()->create();
        $viewer = Role::query()->where('slug', 'viewer')->firstOrFail();
        $viewer->permissions()->attach(Permission::query()->where('slug', 'users.delete')->value('id'));

        $this->seed(BasicsAccessSeeder::class);
        $counts = $this->rowCounts();
        $this->seed(BasicsAccessSeeder::class);

        $this->assertSame($counts, $this->rowCounts());
        $this->assertContains('users.delete', $this->permissionsOf('viewer'));
    }

    /**
     * @return list<string>
     */
    private function permissionsOf(string $slug): array
    {
        /** @var list<string> */
        return Role::query()->where('slug', $slug)->firstOrFail()->permissions()->pluck('slug')->all();
    }

    /**
     * @return array<string, int>
     */
    private function rowCounts(): array
    {
        return array_map(
            static fn (string $table): int => DB::table($table)->count(),
            ['permissions' => 'BAS_permissions', 'roles' => 'BAS_roles', 'permission_role' => 'BAS_permission_role', 'role_user' => 'BAS_role_user', 'menus' => 'BAS_menus', 'menu_permission' => 'BAS_menu_permission'],
        );
    }
}
