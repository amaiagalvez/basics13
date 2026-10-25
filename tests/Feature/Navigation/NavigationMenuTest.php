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
use PHPUnit\Framework\Attributes\DataProvider;
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
        Route::get('_menu/user-types', static fn (): string => Blade::render('<x-basics13::sidebar-menu />'))
            ->middleware('web')->name('user-types.probe');
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

        $this->assertNotContains('basics13', $groups);
        $this->assertNotContains('probe', $groups);
    }

    public function test_a_subgroup_without_visible_links_is_hidden(): void
    {
        $user = User::factory()->withPermissions(['users.view'])->create();
        $groups = $this->visibleGroups($user);

        $this->assertContains('basics13', $groups);
        $this->assertNotContains('basics-auxiliary', $groups);
        $this->assertContains('basics-auxiliary', $this->visibleGroups(User::factory()->withPermissions(['user_types.view'])->create()));
    }

    public function test_the_super_admin_sees_every_item(): void
    {
        $this->assertSame(
            ['admin-dashboard', 'users', 'roles', 'permissions', 'permissions-summary', 'sessions', 'configuration', 'email-configurations', 'email-templates', 'user-types'],
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

    public function test_a_top_level_link_without_permissions_shows_on_its_own(): void
    {
        seedMenus('probe', [['key' => 'probe-top', 'label' => 'Probe top', 'sort' => 1, 'route' => 'dashboard']]);

        $keys = array_map(static fn (array $entry): string => $entry['item']['key'], NavigationMenu::for(User::factory()->create()));

        $this->assertContains('probe-top', $keys);
    }

    public function test_an_account_without_permissions_support_sees_no_guarded_link(): void
    {
        $this->seedProbeMenus(['users.view']);
        $account = new \Illuminate\Foundation\Auth\User;

        $keys = array_map(static fn (array $entry): string => $entry['item']['key'], NavigationMenu::for($account));

        $this->assertNotContains('probe', $keys);
    }

    public function test_a_later_seed_drops_the_items_it_no_longer_lists_and_spares_other_packages(): void
    {
        $this->seedProbeMenus(['users.view']);
        seedMenus('other-probe', [['key' => 'other-probe', 'label' => 'Other probe', 'sort' => 1, 'route' => 'dashboard']]);

        seedMenus('probe', [['key' => 'probe', 'label' => 'Probe', 'sort' => 1]]);

        $this->assertFalse(Menu::query()->where('key', 'probe-link')->exists());
        $this->assertSame(0, DB::table('BAS_menu_permission')->whereNotIn('menu_id', Menu::query()->select('id'))->count());
        $this->assertTrue(Menu::query()->where('key', 'other-probe')->exists());
    }

    /**
     * @return iterable<string, array{list<array{key: string, label: string, sort: int, parent?: string, route?: string}>, string}>
     */
    public static function invalidTrees(): iterable
    {
        yield 'empty list' => [[], 'empty'];
        yield 'key of another package' => [[['key' => 'users', 'label' => 'Probe', 'sort' => 1, 'route' => 'dashboard']], 'users (basics13)'];
        yield 'parent outside the seed' => [[['key' => 'probe-link', 'parent' => 'basics13', 'label' => 'Probe', 'sort' => 1, 'route' => 'dashboard']], 'basics13'];
        yield 'fourth level' => [[
            ['key' => 'probe', 'label' => 'Probe', 'sort' => 1],
            ['key' => 'probe-2', 'parent' => 'probe', 'label' => 'Probe', 'sort' => 1],
            ['key' => 'probe-3', 'parent' => 'probe-2', 'label' => 'Probe', 'sort' => 1],
            ['key' => 'probe-4', 'parent' => 'probe-3', 'label' => 'Probe', 'sort' => 1, 'route' => 'dashboard'],
        ], 'three levels'];
    }

    /**
     * @param  list<array{key: string, label: string, sort: int, parent?: string, route?: string}>  $items
     */
    #[DataProvider('invalidTrees')]
    public function test_an_invalid_tree_fails_the_seed_and_leaves_the_menus_as_they_were(array $items, string $message): void
    {
        $before = Menu::query()->orderBy('key')->pluck('package', 'key')->all();

        try {
            seedMenus('probe', $items);
            $this->fail('The seed accepted an invalid tree.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }

        $this->assertSame($before, Menu::query()->orderBy('key')->pluck('package', 'key')->all());
    }

    public function test_the_groups_holding_the_current_page_are_marked(): void
    {
        $html = (string) $this->actingAs(User::factory()->superAdmin()->create())->get('_menu/user-types')->getContent();

        $this->assertTrue($this->containsCurrent($html, 'basics13'));
        $this->assertTrue($this->containsCurrent($html, 'basics-auxiliary'));
        $this->assertTrue($this->isCurrent($html, 'user-types'));
        $this->assertFalse($this->isCurrent($html, 'users'));
    }

    public function test_the_menu_carries_the_script_that_keeps_folded_groups(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->assertStringContainsString('basics13.menu.closed', Blade::render('<x-basics13::sidebar-menu />'));
    }

    public function test_removing_a_package_menus_drops_its_items(): void
    {
        $this->seedProbeMenus(['users.view']);

        removeMenus('probe');

        $this->assertFalse(Menu::query()->where('package', 'probe')->exists());
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
        $collect = static function (array $entries) use (&$collect, &$keys): void {
            foreach ($entries as $entry) {
                if ($entry['children'] === []) {
                    $keys[] = $entry['item']['key'];
                } else {
                    $collect($entry['children']);
                }
            }
        };
        $collect(NavigationMenu::for($user));

        return $keys;
    }

    /**
     * @return list<string>
     */
    private function visibleGroups(User $user): array
    {
        $keys = [];
        $collect = static function (array $entries) use (&$collect, &$keys): void {
            foreach ($entries as $entry) {
                if ($entry['children'] !== []) {
                    $keys[] = $entry['item']['key'];
                    $collect($entry['children']);
                }
            }
        };
        $collect(NavigationMenu::for($user));

        return $keys;
    }

    private function containsCurrent(string $html, string $key): bool
    {
        $this->assertSame(1, preg_match('/<ui-disclosure\b[^>]*data-menu-key="'.$key.'"[^>]*>/', $html, $tag), "Group {$key} is not painted.");

        return preg_match('/\sdata-contains-current(?![:\w-])/', $tag[0]) === 1;
    }

    private function isCurrent(string $html, string $key): bool
    {
        $this->assertSame(1, preg_match('/<a\b[^>]*data-test="nav-'.$key.'"[^>]*>/', $html, $tag), "nav-{$key} is not painted.");

        // The classes mention `data-current:` too; only the bare attribute marks the current page.
        return preg_match('/\sdata-current(?![:\w-])/', $tag[0]) === 1;
    }
}
