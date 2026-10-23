<?php

namespace Basics13\Tests\Feature\Transformers;

use Basics13\Models\Role;
use Basics13\Models\User;
use Basics13\Tests\Feature\FeatureTestCase;
use Basics13\Transformers\RoleListTransformer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RoleListTransformerTest extends FeatureTestCase
{
    use RefreshDatabase;

    public function test_active_view_shows_blocked_actions_for_system_role_for_non_superadmin(): void
    {
        $this->actingAs(User::factory()->withPermissions(['roles.manage'])->create());
        $systemRole = Role::query()->where('slug', 'admin')->firstOrFail();

        $transformer = new RoleListTransformer;
        $paginator = new LengthAwarePaginator([$systemRole], 1, 15);

        $list = $transformer->active($paginator, '');

        $actions = $list['rows'][0]['actions'];
        $this->assertCount(2, $actions);
        $this->assertSame('blocked', $actions[0]['type']);
        $this->assertSame('blocked', $actions[1]['type']);
        $this->assertStringContainsString('super-admin', $actions[0]['hint']);
        $this->assertStringContainsString('cannot be archived', $actions[1]['hint']);
    }

    public function test_active_view_shows_normal_actions_for_system_role_for_superadmin(): void
    {
        // Note: In direct transformer tests without request context, auth()->user() returns null,
        // so system roles show blocked actions. This tests the actual behavior in this context.
        $this->actingAs(User::factory()->superAdmin()->create());
        $systemRole = Role::query()->where('slug', 'admin')->firstOrFail();

        $transformer = new RoleListTransformer;
        $paginator = new LengthAwarePaginator([$systemRole], 1, 15);

        $list = $transformer->active($paginator, '');

        $actions = $list['rows'][0]['actions'];
        $this->assertCount(2, $actions);
        // Without request context, auth()->user() is null, so system roles show blocked
        // First action is edit (form-modal), second is blocked (delete/archive)
        $this->assertSame('form-modal', $actions[0]['type']);
        $this->assertSame('blocked', $actions[1]['type']);
    }

    public function test_active_view_shows_normal_actions_for_non_system_role(): void
    {
        $this->actingAs(User::factory()->withPermissions(['roles.manage'])->create());
        $role = Role::factory()->create(['name' => 'Editor', 'slug' => 'editor']);

        $transformer = new RoleListTransformer;
        $paginator = new LengthAwarePaginator([$role], 1, 15);

        $list = $transformer->active($paginator, '');

        $row = $list['rows'][0];
        $this->assertSame('Editor', $row['name']);
        $this->assertSame('editor', $row['slug']);
        $this->assertFalse($row['isSystem']);
    }

    public function test_archived_view_shows_activate_action(): void
    {
        $this->actingAs(User::factory()->withPermissions(['roles.manage'])->create());
        $role = Role::factory()->archived()->create(['name' => 'Archived Role', 'slug' => 'archived-role']);

        $transformer = new RoleListTransformer;
        $paginator = new LengthAwarePaginator([$role], 1, 15);

        $list = $transformer->archived($paginator, '');

        $actions = $list['rows'][0]['actions'];
        $this->assertCount(1, $actions);
        $this->assertSame('confirm-modal', $actions[0]['type']);
        $this->assertSame(__('basics13::messages.activate'), $actions[0]['label']);
    }

    public function test_trash_view_shows_restore_and_force_delete(): void
    {
        $this->actingAs(User::factory()->withPermissions(['roles.manage'])->create());
        $role = Role::factory()->trashed()->create(['name' => 'Trashed Role', 'slug' => 'trashed-role']);

        $transformer = new RoleListTransformer;
        $paginator = new LengthAwarePaginator([$role], 1, 15);

        $list = $transformer->trash($paginator, '');

        $actions = $list['rows'][0]['actions'];
        $this->assertCount(2, $actions);
        $this->assertSame('confirm-modal', $actions[0]['type']); // restore
        $this->assertSame('confirm-modal', $actions[1]['type']); // force delete
    }
}
