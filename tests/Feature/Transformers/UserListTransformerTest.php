<?php

namespace Basics13\Tests\Feature\Transformers;

use Basics13\Models\User;
use Basics13\Tests\Feature\FeatureTestCase;
use Basics13\Transformers\UserListTransformer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserListTransformerTest extends FeatureTestCase
{
    use RefreshDatabase;

    public function test_active_view_shows_delete_action_when_users_count_is_zero(): void
    {
        $this->actingAs(User::factory()->withPermissions(['users.manage'])->create());
        $target = User::factory()->create(['name' => 'Target', 'email' => 'target@example.test']);

        $transformer = new UserListTransformer;
        $paginator = new LengthAwarePaginator([$target], 1, 15);

        $list = $transformer->active($paginator, '');
        $actions = $list['rows'][0]['actions'];

        // edit + delete (not archive)
        $this->assertCount(2, $actions);
        $this->assertSame('confirm-modal', $actions[1]['type']);
    }

    public function test_active_view_shows_archive_action_when_users_count_gt_zero(): void
    {
        $this->actingAs(User::factory()->withPermissions(['users.manage'])->create());
        $target = User::factory()->create(['name' => 'Target', 'email' => 'target@example.test']);
        // Create a trashed user that holds no roles but counts for the "users_count" attribute
        // The users_count is loaded from the query which includes trashed users
        $trashedUser = User::factory()->trashed()->create();

        $transformer = new UserListTransformer;
        $paginator = new LengthAwarePaginator([$target], 1, 15);

        $list = $transformer->active($paginator, '');
        $actions = $list['rows'][0]['actions'];

        // edit + archive (because users_count > 0)
        $this->assertCount(2, $actions);
        $this->assertSame('confirm-modal', $actions[1]['type']);
        // The label for archive action is "Move to trash" in this context
        $this->assertSame(__('basics13::messages.move_to_trash'), $actions[1]['label']);
    }

    public function test_impersonate_action_appears_for_superadmin_on_normal_user(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $this->actingAs($superAdmin);

        $target = User::factory()->create(['name' => 'Target User', 'is_super_admin' => false]);

        $transformer = new UserListTransformer;
        $paginator = new LengthAwarePaginator([$target], 1, 15);

        $list = $transformer->active($paginator, '');
        $actions = $list['rows'][0]['actions'];

        $this->assertCount(3, $actions); // edit, delete, impersonate
        $this->assertSame('user-impersonate-'.$target->id, $actions[2]['test']);
        $this->assertSame(__('actions.impersonate'), $actions[2]['label']);
    }

    public function test_impersonate_action_does_not_appear_for_non_superadmin(): void
    {
        $this->actingAs(User::factory()->withPermissions(['users.manage'])->create());

        $target = User::factory()->create(['name' => 'Target User', 'is_super_admin' => false]);

        $transformer = new UserListTransformer;
        $paginator = new LengthAwarePaginator([$target], 1, 15);

        $list = $transformer->active($paginator, '');
        $actions = $list['rows'][0]['actions'];

        $this->assertCount(2, $actions); // edit, delete only
    }

    public function test_impersonate_action_does_not_appear_on_own_row(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $this->actingAs($superAdmin);

        $transformer = new UserListTransformer;
        $paginator = new LengthAwarePaginator([$superAdmin], 1, 15);

        $list = $transformer->active($paginator, '');
        $actions = $list['rows'][0]['actions'];

        $this->assertCount(2, $actions); // edit, delete only (no impersonate on self)
    }

    public function test_impersonate_action_does_not_appear_on_other_superadmin(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $this->actingAs($superAdmin);

        $otherSuperAdmin = User::factory()->superAdmin()->create();

        $transformer = new UserListTransformer;
        $paginator = new LengthAwarePaginator([$otherSuperAdmin], 1, 15);

        $list = $transformer->active($paginator, '');
        $actions = $list['rows'][0]['actions'];

        $this->assertCount(2, $actions); // edit, delete only (no impersonate on other superadmin)
    }

    public function test_impersonate_action_not_in_archived_list(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $this->actingAs($superAdmin);

        $target = User::factory()->create(['name' => 'Target User', 'active' => false]);

        $transformer = new UserListTransformer;
        $paginator = new LengthAwarePaginator([$target], 1, 15);

        $list = $transformer->archived($paginator, '');
        $actions = $list['rows'][0]['actions'];

        $this->assertCount(1, $actions); // activate only
    }

    public function test_impersonate_action_not_in_trash_list(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $this->actingAs($superAdmin);

        $target = User::factory()->trashed()->create(['name' => 'Target User']);

        $transformer = new UserListTransformer;
        $paginator = new LengthAwarePaginator([$target], 1, 15);

        $list = $transformer->trash($paginator, '');
        $actions = $list['rows'][0]['actions'];

        $this->assertCount(2, $actions); // restore, force-delete only
    }
}
