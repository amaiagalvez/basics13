<?php

namespace Basics13\Tests\Feature\Navigation;

use RuntimeException;
use Basics13\Models\Menu;
use Basics13\Models\Role;
use Basics13\Models\User;
use Illuminate\Support\Facades\DB;
use Basics13\Support\NavigationMenu;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Basics13\Tests\Feature\Roles\RoleTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class NavigationMenuTest extends RoleTestCase
{
    use RefreshDatabase;

    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);

        // Pages named like the ones the exclusions guard, so the component paints there.
        Route::get('_menu/summary', static fn (): string => Blade::render('<x-basics13::sidebar-menu />'))
            ->middleware('web')->name('users.permissions-summary.probe');
        Route::get('_menu/sessions', static fn (): string => Blade::render('<x-basics13::sidebar-menu />'))
            ->middleware('web')->name('sessions.dashboard');
    }

    public function test_seeding_twice_duplicates_nothing(): void
    {
        $this->seedProbeMenus(['users.view']);
        $this->seedProbeMenus(['users.view']);

        $this->assertSame(1, Menu::query()->where('key', 'probe-link')->count());
        $this->assertSame(['users.view'], Menu::query()->where('key', 'probe-link')->firstOrFail()->permissions()->pluck('slug')->all());
    }

    public function test_a_later_seed_takes_a_permission_away(): void
    {
        $this->seedProbeMenus(['users.view', 'roles.view']);
        $this->seedProbeMenus(['roles.view']);

        $this->assertSame(['roles.view'], Menu::query()->where('key', 'probe-link')->firstOrFail()->permissions()->pluck('slug')->all());
    }

    public function test_an_unknown_permission_fails_the_seed_and_writes_nothing(): void
    {
        try {
            $this->seedProbeMenus(['probe.view']);
            $this->fail('The seed accepted an unknown permission.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('probe.view', $exception->getMessage());
        }

        $this->assertFalse(Menu::query()->where('key', 'probe-link')->exists());
    }

    public function test_a_delegated_manager_sees_users_and_not_roles(): void
    {
        $keys = $this->visibleKeys(User::factory()->withPermissions(['users.view_own'])->create());

        $this->assertContains('users', $keys);
        $this->assertNotContains('roles', $keys);
    }

    public function test_manage_grants_the_item(): void
    {
        $this->assertContains('roles', $this->visibleKeys(User::factory()->withPermissions(['roles.manage'])->create()));
    }

    public function test_a_group_without_visible_children_is_hidden(): void
    {
        $this->seedProbeMenus([], 'missing.route');

        $groups = array_map(
            static fn (array $entry): string => $entry['item']['key'],
            NavigationMenu::for(User::factory()->create()),
        );

        $this->assertNotContains('administration', $groups);
        $this->assertNotContains('probe', $groups);
    }

    public function test_the_super_admin_sees_every_item(): void
    {
        $this->assertSame(
            ['admin-dashboard', 'users', 'user-types', 'roles', 'permissions', 'permissions-summary', 'sessions', 'configuration'],
            $this->visibleKeys(User::factory()->superAdmin()->create()),
        );
    }

    public function test_archiving_the_role_hides_its_items_on_the_next_request(): void
    {
        $role = Role::factory()->withPermissions(['roles.view'])->create();
        $viewer = User::factory()->create();
        $viewer->assignRole($role);
        $this->assertContains('roles', $this->visibleKeys($viewer));

        $this->actingAs(User::factory()->superAdmin()->create())
            ->patch(route('roles.archive', $role))
            ->assertRedirect();

        $this->assertNotContains('roles', $this->visibleKeys(User::query()->whereKey($viewer->id)->firstOrFail()));
    }

    public function test_painting_the_menu_again_runs_no_query(): void
    {
        $user = User::factory()->withPermissions(['roles.view'])->create();
        $this->actingAs($user);
        Blade::render('<x-basics13::sidebar-menu />');

        DB::enableQueryLog();
        $html = Blade::render('<x-basics13::sidebar-menu />');

        $this->assertSame([], DB::getQueryLog());
        $this->assertStringContainsString('data-test="nav-roles"', $html);
    }

    public function test_labels_follow_the_active_language(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        app()->setLocale('eu');

        $this->assertStringContainsString(e(__('Permissions summary')), Blade::render('<x-basics13::sidebar-menu />'));
        $this->assertNotSame('Permissions summary', __('Permissions summary'));
    }

    public function test_users_is_not_current_on_the_permissions_summary(): void
    {
        $html = $this->actingAs(User::factory()->superAdmin()->create())->get('_menu/summary')->getContent();
        $html = (string) $html;

        $this->assertTrue($this->isCurrent($html, 'permissions-summary'));
        $this->assertFalse($this->isCurrent($html, 'users'));
    }

    public function test_sessions_is_not_current_on_the_administration_dashboard(): void
    {
        $html = $this->actingAs(User::factory()->superAdmin()->create())->get('_menu/sessions')->getContent();
        $html = (string) $html;

        $this->assertTrue($this->isCurrent($html, 'admin-dashboard'));
        $this->assertFalse($this->isCurrent($html, 'sessions'));
    }

    /**
     * @param  list<string>  $permissions
     */
    private function seedProbeMenus(array $permissions, string $route = 'dashboard'): void
    {
        seedMenus('probe', [
            ['key' => 'probe', 'label' => 'Probe', 'sort' => 1],
            ['key' => 'probe-link', 'parent' => 'probe', 'label' => 'Probe link', 'sort' => 1, 'route' => $route, 'permissions' => $permissions],
        ]);
    }

    /**
     * @return list<string>
     */
    private function visibleKeys(User $user): array
    {
        $keys = [];

        foreach (NavigationMenu::for($user) as $entry) {
            foreach ($entry['children'] as $item) {
                $keys[] = $item['key'];
            }
        }

        return $keys;
    }

    private function isCurrent(string $html, string $key): bool
    {
        $this->assertSame(1, preg_match('/<a\b[^>]*data-test="nav-'.$key.'"[^>]*>/', $html, $tag), "nav-{$key} is not painted.");

        // The classes mention `data-current:` too; only the bare attribute marks the current page.
        return preg_match('/\sdata-current(?![:\w-])/', $tag[0]) === 1;
    }
}
