<?php

namespace Basics13\Tests\Feature\Queries\Users;

use Basics13\Models\Role;
use Basics13\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Basics13\Tests\Feature\FeatureTestCase;
use Illuminate\Pagination\LengthAwarePaginator;
use Basics13\Queries\Users\PermissionSummaryQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PermissionSummaryQueryTest extends FeatureTestCase
{
    use RefreshDatabase;

    public function test_page_returns_active_users_excluding_super_admin(): void
    {
        $actor = User::factory()->withPermissions(['roles.view'])->create();
        $this->actingAs($actor);
        User::factory()->create(['name' => 'User 1', 'email' => 'user1@example.test']);
        User::factory()->create(['name' => 'User 2', 'email' => 'user2@example.test']);
        User::factory()->superAdmin()->create(['name' => 'Super Admin', 'email' => 'admin@example.test']);
        User::factory()->create(['name' => 'Inactive User', 'email' => 'inactive@example.test', 'active' => false]);

        $query = new PermissionSummaryQuery;
        /** @var LengthAwarePaginator<int, User> $paginator */
        $paginator = $query->page('');

        // The actor is also an active user, so total should be 3 (actor + 2 created)
        $this->assertSame(3, $paginator->total());
        $this->assertFalse(in_array('admin@example.test', array_column($paginator->items(), 'email')));
        $this->assertFalse(in_array('inactive@example.test', array_column($paginator->items(), 'email')));
    }

    public function test_page_with_search_filters_by_name_and_email(): void
    {
        // A fixed actor: a random name such as "Diane" would also match the search.
        $this->actingAs(User::factory()->withPermissions(['roles.view'])->create(['name' => 'Actor', 'email' => 'actor@example.test']));
        User::factory()->create(['name' => 'Ane Martinez', 'email' => 'ane@example.test']);
        User::factory()->create(['name' => 'Jon Lopez', 'email' => 'jon@example.test']);

        $query = new PermissionSummaryQuery;

        $byName = $query->page('Ane');
        $this->assertSame(1, $byName->total());
        /** @var User $firstByName */
        $firstByName = $byName->first();
        $this->assertSame('Ane Martinez', $firstByName->name);

        $byEmail = $query->page('jon@');
        $this->assertSame(1, $byEmail->total());
        /** @var User $firstByEmail */
        $firstByEmail = $byEmail->first();
        $this->assertSame('Jon Lopez', $firstByEmail->name);
    }

    public function test_all_returns_lazy_collection(): void
    {
        $this->actingAs(User::factory()->withPermissions(['roles.view'])->create());
        // Super admin is created by seed data
        User::factory()->count(5)->create();

        $query = new PermissionSummaryQuery;
        $collection = $query->all('');

        $this->assertInstanceOf(LazyCollection::class, $collection);
        // 5 created + 1 from actingAs (if not super admin)
        $this->assertGreaterThanOrEqual(5, $collection->count());
    }

    public function test_modules_returns_distinct_modules(): void
    {
        $this->actingAs(User::factory()->withPermissions(['roles.view'])->create());

        $query = new PermissionSummaryQuery;
        $modules = $query->modules();

        $this->assertInstanceOf(Collection::class, $modules);
        $this->assertNotEmpty($modules);
        $this->assertTrue($modules->contains('users'));
        $this->assertTrue($modules->contains('roles'));
    }

    public function test_searchable_fields_returns_expected_fields(): void
    {
        $query = new PermissionSummaryQuery;
        $fields = $query->searchableFields('active');

        $this->assertArrayHasKey('name', $fields);
        $this->assertArrayHasKey('email', $fields);
        $this->assertArrayHasKey('role_ids', $fields);

        $this->assertSame('text', $fields['name']['type']);
        $this->assertSame('text', $fields['email']['type']);
        $this->assertSame('select', $fields['role_ids']['type']);
        $this->assertTrue($fields['role_ids']['multiple'] ?? false);
    }

    public function test_searchable_fields_returns_same_for_all_scopes(): void
    {
        $query = new PermissionSummaryQuery;

        $active = $query->searchableFields('active');
        $archived = $query->searchableFields('archived');
        $trashed = $query->searchableFields('trashed');

        // The field definitions are the same for all scopes (only field keys compared)
        $this->assertSame(array_keys($active), array_keys($archived));
        $this->assertSame(array_keys($active), array_keys($trashed));
        $this->assertSame($active['name'], $archived['name']);
        $this->assertSame($active['email'], $archived['email']);
        $this->assertSame($active['role_ids']['column'], $archived['role_ids']['column']);
    }

    public function test_all_with_filters_applies_them(): void
    {
        $this->actingAs(User::factory()->withPermissions(['roles.view'])->create());
        $role = Role::factory()->create(['name' => 'Editor']);
        User::factory()->create(['name' => 'User With Role', 'email' => 'with-role@example.test'])->assignRole($role);
        User::factory()->create(['name' => 'User Without Role', 'email' => 'without-role@example.test']);

        $query = new PermissionSummaryQuery;
        $collection = $query->all('', ['role_ids' => ['operator' => 'equals', 'value' => [$role->id]]]);

        $this->assertInstanceOf(LazyCollection::class, $collection);
        $this->assertSame(1, $collection->count());
        /** @var User $firstUser */
        $firstUser = $collection->first();
        $this->assertSame('User With Role', $firstUser->name);
    }

    public function test_accounts_query_loads_active_roles_with_permissions(): void
    {
        $this->actingAs(User::factory()->withPermissions(['roles.view'])->create());
        $role = Role::factory()->withPermissions(['users.view', 'users.edit'])->create();
        $user = User::factory()->create(['name' => 'Test User', 'email' => 'test-user@example.test']);
        $user->assignRole($role);

        $query = new PermissionSummaryQuery;
        $paginator = $query->page('');

        /** @var User $userFromQuery */
        $userFromQuery = $paginator->firstWhere('email', 'test-user@example.test');
        $this->assertTrue($userFromQuery->relationLoaded('roles'));
        $this->assertSame(1, $userFromQuery->roles->count());
        $this->assertTrue($userFromQuery->roles->first()->relationLoaded('permissions'));
        $this->assertSame(2, $userFromQuery->roles->first()->permissions->count());
    }

    public function test_integration_search_columns_returns_base_columns(): void
    {
        $query = new PermissionSummaryQuery;

        $reflection = new \ReflectionClass($query);
        $method = $reflection->getMethod('integration');
        $method->setAccessible(true);
        $integration = $method->invoke($query);

        $baseColumns = ['name', 'email'];
        $result = $integration->searchColumns('active', $baseColumns);

        $this->assertSame($baseColumns, $result);
    }

    public function test_get_model_class_returns_user_model(): void
    {
        $query = new PermissionSummaryQuery;

        $reflection = new \ReflectionClass($query);
        $method = $reflection->getMethod('getModelClass');
        $method->setAccessible(true);
        $modelClass = $method->invoke($query);

        $this->assertSame(User::class, $modelClass);
    }
}
