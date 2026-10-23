<?php

namespace Basics13\Tests\Feature\Roles;

use Basics13\Models\Role;
use Basics13\Models\Permission;
use Illuminate\Support\Facades\DB;
use Basics13\Tests\Feature\FeatureTestCase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SessionSettingPermissionSeedTest extends FeatureTestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_session_and_setting_permissions_and_grants_manage_to_admin(): void
    {
        $this->assertEqualsCanonicalizing(
            ['sessions.view', 'sessions.manage'],
            Permission::query()->where('module', 'sessions')->pluck('slug')->all(),
        );
        $this->assertEqualsCanonicalizing(
            ['settings.view', 'settings.edit', 'settings.manage'],
            Permission::query()->where('module', 'settings')->pluck('slug')->all(),
        );

        $this->assertEqualsCanonicalizing(
            ['roles.manage', 'sessions.manage', 'settings.manage', 'users.manage'],
            Role::query()->where('slug', 'admin')->firstOrFail()->permissions()
                ->whereIn('module', ['users', 'roles', 'sessions', 'settings'])->pluck('slug')->all(),
        );
    }

    public function test_seeding_again_duplicates_nothing_and_keeps_the_permissions_roles_were_given(): void
    {
        $viewer = Role::query()->where('slug', 'viewer')->firstOrFail();
        $viewer->permissions()->attach(Permission::query()->where('slug', 'sessions.view')->value('id'));

        $this->runSeedMigration();
        $counts = $this->rowCounts();
        $this->runSeedMigration();

        $this->assertSame($counts, $this->rowCounts());
        $this->assertContains('sessions.view', $viewer->permissions()->pluck('slug')->all());
    }

    private function runSeedMigration(): void
    {
        $migration = require dirname(__DIR__, 3).'/database/migrations/2026_10_23_000003_seed_session_and_setting_permissions.php';

        if (! $migration instanceof Migration || ! method_exists($migration, 'up')) {
            $this->fail('The seed migration must define up().');
        }

        $migration->up();
    }

    /**
     * @return array<string, int>
     */
    private function rowCounts(): array
    {
        return array_map(
            static fn (string $table): int => DB::table($table)->count(),
            ['permissions' => 'BAS_permissions', 'permission_role' => 'BAS_permission_role'],
        );
    }
}
