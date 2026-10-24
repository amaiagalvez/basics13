<?php

namespace Basics13\Tests\Unit\Transformers;

use Basics13\Models\User;
use Basics13\Tests\TestCase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Basics13\Support\Testing\PermissionsActor;
use Basics13\Transformers\UserListTransformer;
use Illuminate\Pagination\LengthAwarePaginator;

class UserListTransformerImpersonateTest extends TestCase
{
    protected function actingAsSuperAdmin(int $id = 1): PermissionsActor
    {
        $superAdmin = PermissionsActor::superAdmin($id);
        Auth::shouldReceive('id')->andReturn($id);
        Auth::shouldReceive('user')->andReturn($superAdmin);

        return $superAdmin;
    }

    protected function actingAsRegularUser(int $id = 1): PermissionsActor
    {
        $actor = PermissionsActor::holding(['users.view', 'users.manage'], $id);
        Auth::shouldReceive('id')->andReturn($id);
        Auth::shouldReceive('user')->andReturn($actor);

        return $actor;
    }

    public function test_impersonate_action_appears_for_superadmin_on_normal_user(): void
    {
        $this->actingAsSuperAdmin(1);

        $transformer = new UserListTransformer;

        $target = User::factory()->make(['id' => 2, 'name' => 'Target User', 'is_super_admin' => false, 'active' => true]);
        /** @var LengthAwarePaginator<int, Model> $records */
        $records = new LengthAwarePaginator(
            new Collection([$target]),
            1,
            15,
            1,
            ['path' => 'users']
        );

        $result = $transformer->active($records, '');
        $actions = $result['rows'][0]['actions'];

        $this->assertCount(3, $actions); // edit, delete, impersonate
        $this->assertEquals('user-impersonate-2', $actions[2]['test']);
        $this->assertEquals(__('actions.impersonate'), $actions[2]['label']);
    }

    public function test_impersonate_action_does_not_appear_for_non_superadmin(): void
    {
        $this->actingAsRegularUser(1);

        $transformer = new UserListTransformer;

        $target = User::factory()->make(['id' => 2, 'name' => 'Target User', 'is_super_admin' => false, 'active' => true]);
        /** @var LengthAwarePaginator<int, Model> $records */
        $records = new LengthAwarePaginator(
            new Collection([$target]),
            1,
            15,
            1,
            ['path' => 'users']
        );

        $result = $transformer->active($records, '');
        $actions = $result['rows'][0]['actions'];

        $this->assertCount(2, $actions); // edit, delete only
    }

    public function test_impersonate_action_does_not_appear_on_own_row(): void
    {
        $this->actingAsSuperAdmin(1);

        $transformer = new UserListTransformer;

        $target = User::factory()->make(['id' => 1, 'name' => 'Super Admin', 'is_super_admin' => true, 'active' => true]);
        /** @var LengthAwarePaginator<int, Model> $records */
        $records = new LengthAwarePaginator(
            new Collection([$target]),
            1,
            15,
            1,
            ['path' => 'users']
        );

        $result = $transformer->active($records, '');
        $actions = $result['rows'][0]['actions'];

        $this->assertCount(2, $actions); // edit, delete only (no impersonate on self)
    }

    public function test_impersonate_action_does_not_appear_on_other_superadmin(): void
    {
        $this->actingAsSuperAdmin(1);

        $transformer = new UserListTransformer;

        $target = User::factory()->make(['id' => 3, 'name' => 'Other Super Admin', 'is_super_admin' => true, 'active' => true]);
        /** @var LengthAwarePaginator<int, Model> $records */
        $records = new LengthAwarePaginator(
            new Collection([$target]),
            1,
            15,
            1,
            ['path' => 'users']
        );

        $result = $transformer->active($records, '');
        $actions = $result['rows'][0]['actions'];

        $this->assertCount(2, $actions); // edit, delete only (no impersonate on other superadmin)
    }

    public function test_impersonate_action_not_in_archived_list(): void
    {
        $this->actingAsSuperAdmin(1);

        $transformer = new UserListTransformer;

        $target = User::factory()->make(['id' => 2, 'name' => 'Target User', 'is_super_admin' => false, 'active' => false]);
        /** @var LengthAwarePaginator<int, Model> $records */
        $records = new LengthAwarePaginator(
            new Collection([$target]),
            1,
            15,
            1,
            ['path' => 'users/archived']
        );

        $result = $transformer->archived($records, '');
        $actions = $result['rows'][0]['actions'];

        $this->assertCount(1, $actions); // activate only
    }

    public function test_impersonate_action_not_in_trash_list(): void
    {
        $this->actingAsSuperAdmin(1);

        $transformer = new UserListTransformer;

        $target = User::factory()->make(['id' => 2, 'name' => 'Target User', 'is_super_admin' => false, 'deleted_at' => now()]);
        /** @var LengthAwarePaginator<int, Model> $records */
        $records = new LengthAwarePaginator(
            new Collection([$target]),
            1,
            15,
            1,
            ['path' => 'users/trash']
        );

        $result = $transformer->trash($records, '');
        $actions = $result['rows'][0]['actions'];

        $this->assertCount(2, $actions); // restore, force-delete only
    }
}
