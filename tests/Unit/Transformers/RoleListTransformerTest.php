<?php

namespace Basics13\Tests\Unit\Transformers;

use Basics13\Models\Role;
use Basics13\Tests\TestCase;
use Basics13\Models\Permission;
use Illuminate\Support\Facades\Route;
use Illuminate\Database\Eloquent\Collection;
use Basics13\Transformers\RoleListTransformer;

class RoleListTransformerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('roles', fn () => null)->name('roles.index');
        Route::get('roles/archived', fn () => null)->name('roles.archived.index');
        Route::get('roles/trash', fn () => null)->name('roles.trash.index');
        Route::delete('roles/{record}', fn () => null)->name('roles.destroy');
        Route::patch('roles/{record}/archive', fn () => null)->name('roles.archive');
        Route::patch('roles/archived/{record}', fn () => null)->name('roles.archived.activate');
        Route::patch('roles/trash/{record}', fn () => null)->name('roles.trash.restore');
        Route::delete('roles/trash/{record}', fn () => null)->name('roles.trash.destroy');
    }

    public function test_get_delete_block_condition_returns_true_when_users_count_gt_0(): void
    {
        $transformer = new RoleListTransformer;

        $role = new Role(['id' => 1, 'name' => 'Test']);
        $role->setAttribute('users_count', 5);

        $reflection = new \ReflectionMethod($transformer, 'getDeleteBlockCondition');
        $reflection->setAccessible(true);

        $this->assertTrue($reflection->invoke($transformer, $role));
    }

    public function test_get_delete_block_condition_returns_false_when_users_count_0(): void
    {
        $transformer = new RoleListTransformer;

        $role = new Role(['id' => 1, 'name' => 'Test']);
        $role->setAttribute('users_count', 0);

        $reflection = new \ReflectionMethod($transformer, 'getDeleteBlockCondition');
        $reflection->setAccessible(true);

        $this->assertFalse($reflection->invoke($transformer, $role));
    }

    public function test_get_force_delete_block_condition_returns_true_when_users_count_gt_0(): void
    {
        $transformer = new RoleListTransformer;

        $role = new Role(['id' => 1, 'name' => 'Test']);
        $role->setAttribute('users_count', 5);

        $reflection = new \ReflectionMethod($transformer, 'getForceDeleteBlockCondition');
        $reflection->setAccessible(true);

        $this->assertTrue($reflection->invoke($transformer, $role));
    }

    public function test_get_force_delete_block_hint_returns_correct_message(): void
    {
        $transformer = new RoleListTransformer;

        $role = new Role(['id' => 1, 'name' => 'Test']);

        $reflection = new \ReflectionMethod($transformer, 'getForceDeleteBlockHint');
        $reflection->setAccessible(true);

        $this->assertSame(__('basics13::messages.cannot_force_delete_related'), $reflection->invoke($transformer, $role));
    }

    public function test_edit_payload_includes_permission_ids_when_loaded(): void
    {
        $transformer = new RoleListTransformer;

        $permission1 = new Permission(['id' => 1, 'slug' => 'users.view']);
        $permission2 = new Permission(['id' => 2, 'slug' => 'users.edit']);
        $permission1->setAttribute('id', 1);
        $permission2->setAttribute('id', 2);
        $role = new Role(['id' => 1, 'name' => 'Test', 'slug' => 'test', 'description' => '', 'notes' => '', 'is_system' => false]);
        $role->setRelation('permissions', new Collection([$permission1, $permission2]));

        $reflection = new \ReflectionMethod($transformer, 'editPayload');
        $reflection->setAccessible(true);

        $payload = $reflection->invoke($transformer, $role);

        $this->assertEqualsCanonicalizing(['1', '2'], $payload['permission_ids']);
    }

    public function test_edit_payload_returns_empty_when_permissions_not_loaded(): void
    {
        $transformer = new RoleListTransformer;

        $role = new Role(['id' => 1, 'name' => 'Test', 'slug' => 'test', 'description' => '', 'notes' => '', 'is_system' => false]);
        // permissions relation not loaded

        $reflection = new \ReflectionMethod($transformer, 'editPayload');
        $reflection->setAccessible(true);

        $payload = $reflection->invoke($transformer, $role);

        $this->assertSame([], $payload['permission_ids']);
    }

    public function test_resource_label_returns_roles(): void
    {
        $transformer = new RoleListTransformer;

        $reflection = new \ReflectionMethod($transformer, 'resourceLabel');
        $reflection->setAccessible(true);

        $this->assertSame(__('Roles'), $reflection->invoke($transformer));
    }

    public function test_resource_key_returns_role(): void
    {
        $transformer = new RoleListTransformer;

        $reflection = new \ReflectionMethod($transformer, 'resourceKey');
        $reflection->setAccessible(true);

        $this->assertSame('role', $reflection->invoke($transformer));
    }

    public function test_columns_returns_expected_fields(): void
    {
        $transformer = new RoleListTransformer;

        $role = new Role([
            'id' => 1,
            'name' => 'Test Role',
            'slug' => 'test-role',
            'description' => 'A test role',
            'is_system' => false,
        ]);
        $role->setAttribute('id', 1);
        $role->setAttribute('users_count', 3);
        $role->setAttribute('permissions_count', 2);
        $role->setRelation('permissions', new Collection);

        $reflection = new \ReflectionMethod($transformer, 'columns');
        $reflection->setAccessible(true);

        $columns = $reflection->invoke($transformer, $role, []);

        $this->assertSame(1, $columns['id']);
        $this->assertSame('Test Role', $columns['name']);
        $this->assertSame('test-role', $columns['slug']);
        $this->assertSame('A test role', $columns['description']);
        $this->assertFalse($columns['isSystem']);
        $this->assertSame(3, $columns['usersCount']);
        $this->assertSame(2, $columns['permissionsCount']);
        $this->assertArrayHasKey('editPayload', $columns);
    }
}
