<?php

namespace Basics13\Tests\Browser\Roles;

use Basics13\Models\Role;
use Laravel\Dusk\Browser;
use Basics13\Models\Permission;
use Basics13\Support\Testing\DuskTestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;

/**
 * The slug follows the name in Alpine until somebody types in it, and the permission checkboxes are
 * bound to the drawer state: both only happen in the browser.
 */
class RoleFormTest extends DuskTestCase
{
    use DatabaseMigrations;

    private const NAME_SELECTOR = 'dialog[open] [data-test="role-name"]';

    private const SLUG_SELECTOR = 'dialog[open] [data-test="role-slug"]';

    private const SUBMIT_SELECTOR = 'dialog[open] [data-test="role-submit"]';

    private const STATUS_SELECTOR = '[data-test="role-status"]';

    public function test_the_slug_follows_the_name_until_it_is_typed_and_the_permissions_are_saved(): void
    {
        $user = $this->admin();

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user)
                ->visit('/roles')
                ->click('[data-test="role-create-button"]')
                ->waitFor(self::NAME_SELECTOR)
                ->type(self::NAME_SELECTOR, 'Área técnica')
                ->assertInputValue(self::SLUG_SELECTOR, 'area-tecnica')
                ->clear(self::SLUG_SELECTOR)
                ->type(self::SLUG_SELECTOR, 'tech')
                ->type(self::NAME_SELECTOR, 'Área técnica nueva')
                ->assertInputValue(self::SLUG_SELECTOR, 'tech')
                ->check('dialog[open] [data-test="role-permission-users.view"]')
                ->check('dialog[open] [data-test="role-permission-roles.view"]')
                ->click(self::SUBMIT_SELECTOR)
                ->waitFor(self::STATUS_SELECTOR);
        });

        $role = Role::query()->where('slug', 'tech')->firstOrFail();
        $this->assertSame('Área técnica nueva', $role->name);
        $this->assertEqualsCanonicalizing(['users.view', 'roles.view'], $role->permissions()->pluck('slug')->all());
    }

    public function test_editing_keeps_the_slug_and_saves_the_permission_changes(): void
    {
        $user = $this->admin();
        $role = Role::factory()->withPermissions(['users.view'])->create(['name' => 'Editors', 'slug' => 'editors']);

        $this->browse(function (Browser $browser) use ($user, $role): void {
            $browser->loginAs($user)
                ->visit('/roles')
                ->click("[data-test='role-edit-{$role->id}']")
                ->waitFor(self::NAME_SELECTOR)
                ->assertChecked('dialog[open] [data-test="role-permission-users.view"]')
                ->type(self::NAME_SELECTOR, 'Account editors')
                ->assertInputValue(self::SLUG_SELECTOR, 'editors')
                ->uncheck('dialog[open] [data-test="role-permission-users.view"]')
                ->check('dialog[open] [data-test="role-permission-users.edit"]')
                ->click(self::SUBMIT_SELECTOR)
                ->waitFor(self::STATUS_SELECTOR);
        });

        $role->refresh();
        $this->assertSame('Account editors', $role->name);
        $this->assertSame('editors', $role->slug);
        $this->assertSame([Permission::query()->where('slug', 'users.edit')->value('id')], $role->permissions()->pluck('BAS_permissions.id')->all());
    }

    public function test_the_slug_of_a_system_role_is_read_only_for_the_super_admin(): void
    {
        $superAdmin = $this->user();
        $superAdmin->forceFill(['is_super_admin' => true])->save();
        $admin = Role::query()->where('slug', 'admin')->firstOrFail();

        $this->browse(function (Browser $browser) use ($superAdmin, $admin): void {
            $browser->loginAs($superAdmin)
                ->visit('/roles')
                ->click("[data-test='role-edit-{$admin->id}']")
                ->waitFor(self::NAME_SELECTOR)
                ->type(self::NAME_SELECTOR, 'Administrators')
                ->assertInputValue(self::SLUG_SELECTOR, 'admin')
                ->assertScript('document.querySelector('.json_encode(self::SLUG_SELECTOR).').readOnly', true)
                ->assertVisible('dialog[open] [data-test="role-slug-system"]');
        });
    }
}
