<?php

namespace Basics13\Tests\Feature\Roles;

use Basics13\Models\Role;
use Basics13\Models\User;
use Basics13\Models\Permission;
use Illuminate\Support\Facades\DB;
use Basics13\Tests\Feature\FeatureTestCase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RolePermissionSeedTest extends FeatureTestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_user_and_role_permissions_and_the_three_starting_roles(): void
    {
        $this->assertSame(18, Permission::query()->whereIn('module', ['roles', 'users'])->count());
        // The session log and the configuration seed their own modules in a later migration.
        $this->assertSame(['roles', 'sessions', 'settings', 'users'], Permission::query()->distinct()->orderBy('module')->pluck('module')->all());

        $this->assertEqualsCanonicalizing(['users.manage', 'roles.manage', 'sessions.manage', 'settings.manage'], $this->permissionsOf('admin'));
        $this->assertEqualsCanonicalizing(['users.view', 'users.create', 'users.edit', 'roles.view'], $this->permissionsOf('manager'));
        $this->assertEqualsCanonicalizing(['users.view', 'roles.view'], $this->permissionsOf('viewer'));

        $this->assertTrue(Role::query()->where('slug', 'admin')->value('is_system'));
        $this->assertSame(0, Role::query()->where('slug', '!=', 'admin')->where('is_system', true)->count());
    }

    public function test_existing_accounts_outside_the_trash_receive_the_admin_role(): void
    {
        $active = User::factory()->create();
        $archived = User::factory()->archived()->create();
        $trashed = User::factory()->trashed()->create();
        $superAdmin = User::factory()->superAdmin()->create();

        $this->runSeedMigration();

        $this->assertSame(['admin'], $active->roles()->pluck('slug')->all());
        $this->assertSame(['admin'], $archived->roles()->pluck('slug')->all());
        $this->assertSame([], $trashed->roles()->pluck('slug')->all());
        $this->assertSame([], $superAdmin->roles()->pluck('slug')->all());
        $this->assertTrue($active->hasPermissionTo('users.edit'));
    }

    public function test_seeding_again_duplicates_nothing_and_keeps_the_permissions_roles_were_given(): void
    {
        User::factory()->create();
        $viewer = Role::query()->where('slug', 'viewer')->firstOrFail();
        $viewer->permissions()->attach(Permission::query()->where('slug', 'users.delete')->value('id'));

        $this->runSeedMigration();
        $counts = $this->rowCounts();
        $this->runSeedMigration();

        $this->assertSame($counts, $this->rowCounts());
        $this->assertContains('users.delete', $this->permissionsOf('viewer'));
    }

    private function runSeedMigration(): void
    {
        $migration = require dirname(__DIR__, 3).'/database/migrations/2026_10_22_000006_seed_user_and_role_permissions.php';

        if (! $migration instanceof Migration || ! method_exists($migration, 'up')) {
            $this->fail('The seed migration must define up().');
        }

        $migration->up();
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
            ['permissions' => 'BAS_permissions', 'roles' => 'BAS_roles', 'permission_role' => 'BAS_permission_role', 'role_user' => 'BAS_role_user'],
        );
    }
}
