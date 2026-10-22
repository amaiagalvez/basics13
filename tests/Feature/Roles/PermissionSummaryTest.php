<?php

namespace Basics13\Tests\Feature\Roles;

use Basics13\Models\Role;
use Basics13\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PermissionSummaryTest extends RoleTestCase
{
    use RefreshDatabase;

    public function test_each_account_shows_the_union_of_its_active_roles_and_manage_as_full_access(): void
    {
        $this->actingAs(User::factory()->withPermissions(['roles.view'])->create(['name' => 'Zzz actor']));
        $account = User::factory()->create(['name' => 'Ane']);
        $account->assignRole(Role::factory()->withPermissions(['users.view'])->create(['name' => 'Readers']));
        $account->assignRole(Role::factory()->withPermissions(['roles.manage'])->create(['name' => 'Role admins']));
        $account->assignRole(Role::factory()->archived()->withPermissions(['users.edit'])->create(['name' => 'Archived editors']));

        $this->get(route('users.permissions-summary.index'))
            ->assertOk()
            ->assertViewHas('rows', function (array $rows) use ($account): bool {
                $row = collect($rows)->firstWhere('id', $account->id);

                return $row['roles'] === ['Readers', 'Role admins']
                    && $row['modules']['users'] === ['full' => false, 'permissions' => [__('users.view')]]
                    && $row['modules']['roles'] === ['full' => true, 'permissions' => []];
            })
            ->assertSeeInOrder(['data-test="permission-summary-roles-'.$account->id.'"', 'Readers', 'Role admins'], false);
    }

    public function test_the_super_admin_is_left_out(): void
    {
        $this->actingAs(User::factory()->withPermissions(['roles.view'])->create());
        User::factory()->superAdmin()->create(['name' => 'Hidden root']);

        $this->get(route('users.permissions-summary.index'))->assertOk()->assertDontSee('Hidden root');
        $this->get(route('users.permissions-summary.export'))->assertOk()->assertDontSee('Hidden root');
    }

    public function test_the_summary_is_filtered_by_role_and_by_search(): void
    {
        $this->actingAs(User::factory()->withPermissions(['roles.view'])->create(['name' => 'Zzz actor']));
        $viewer = Role::query()->where('slug', 'viewer')->firstOrFail();
        User::factory()->create(['name' => 'Ane viewer'])->assignRole($viewer);
        User::factory()->create(['name' => 'Jon nobody']);

        $byRole = ['filters' => ['role_ids' => ['operator' => 'equals', 'value' => [(string) $viewer->id]]]];

        $this->get(route('users.permissions-summary.index', $byRole))
            ->assertOk()
            ->assertSee('Ane viewer')
            ->assertDontSee('Jon nobody');

        $this->get(route('users.permissions-summary.index', ['search' => 'Jon']))
            ->assertOk()
            ->assertSee('Jon nobody')
            ->assertDontSee('Ane viewer');
    }

    public function test_the_csv_holds_exactly_the_filtered_rows(): void
    {
        $this->actingAs(User::factory()->withPermissions(['roles.view'])->create(['name' => 'Zzz actor']));
        $viewer = Role::query()->where('slug', 'viewer')->firstOrFail();
        User::factory()->create(['name' => 'Ane viewer', 'email' => 'ane@example.test'])->assignRole($viewer);
        User::factory()->create(['name' => 'Jon nobody']);
        $byRole = ['filters' => ['role_ids' => ['operator' => 'equals', 'value' => [(string) $viewer->id]]]];

        $this->get(route('users.permissions-summary.index', $byRole))
            ->assertSee(e(route('users.permissions-summary.export', $byRole)), false);

        $response = $this->get(route('users.permissions-summary.export', $byRole));

        $response->assertOk()->assertDownload('permissions-summary.csv');
        $lines = array_values(array_filter(explode("\n", $response->streamedContent())));
        $this->assertCount(2, $lines);
        $this->assertSame(str_getcsv($lines[0], escape: '')[0], __('Name'));
        $this->assertSame(
            ['Ane viewer', 'ane@example.test', 'Viewer', __('roles.view'), __('users.view')],
            str_getcsv($lines[1], escape: ''),
        );
    }

    public function test_a_formula_typed_as_a_name_is_exported_as_text(): void
    {
        $this->actingAs(User::factory()->withPermissions(['roles.view'])->create(['name' => 'Zzz actor']));
        User::factory()->create(['name' => '=HYPERLINK("http://evil.test")']);

        $response = $this->get(route('users.permissions-summary.export', ['search' => 'HYPERLINK']));

        $this->assertStringContainsString("'=HYPERLINK", $response->streamedContent());
    }

    public function test_the_summary_needs_roles_view(): void
    {
        $this->actingAs(User::factory()->withPermissions(['users.manage'])->create());

        $this->get(route('users.permissions-summary.index'))->assertForbidden();
        $this->get(route('users.permissions-summary.export'))->assertForbidden();
    }
}
