<?php

namespace Basics13\Tests\Feature\Queries\Roles;

use Basics13\Models\Role;
use Basics13\Models\User;
use Basics13\Queries\Roles\RoleListQuery;
use Basics13\Tests\Feature\FeatureTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RoleListQueryTest extends FeatureTestCase
{
    use RefreshDatabase;

    public function test_active_returns_active_roles(): void
    {
        $this->actingAs(User::factory()->withPermissions(['roles.view'])->create());
        Role::factory()->create(['name' => 'Active Role', 'slug' => 'active-role']);
        Role::factory()->archived()->create(['name' => 'Archived Role', 'slug' => 'archived-role']);
        Role::factory()->trashed()->create(['name' => 'Trashed Role', 'slug' => 'trashed-role']);

        $query = new RoleListQuery;
        $paginator = $query->active('');

        // There are system roles (admin, viewer, etc.) from seed data
        $this->assertGreaterThanOrEqual(1, $paginator->total());
        $this->assertTrue(in_array('active-role', array_column($paginator->items(), 'slug')));
    }

    public function test_active_with_search_filters_by_name_slug_description(): void
    {
        $this->actingAs(User::factory()->withPermissions(['roles.view'])->create());
        Role::factory()->create(['name' => 'Editors', 'slug' => 'editors', 'description' => 'Edit content']);
        Role::factory()->create(['name' => 'Admins', 'slug' => 'admins', 'description' => 'Admin everything']);

        $query = new RoleListQuery;

        $byName = $query->active('Editors');
        $this->assertSame(1, $byName->total());
        $this->assertSame('Editors', $byName->first()->name);

        $bySlug = $query->active('admins');
        $this->assertSame(1, $bySlug->total());
        $this->assertSame('Admins', $bySlug->first()->name);

        $byDescription = $query->active('content');
        $this->assertSame(1, $byDescription->total());
        $this->assertSame('Editors', $byDescription->first()->name);
    }

    public function test_archived_returns_archived_roles(): void
    {
        $this->actingAs(User::factory()->withPermissions(['roles.view'])->create());
        Role::factory()->create(['name' => 'Active Role', 'slug' => 'active-role']);
        Role::factory()->archived()->create(['name' => 'Archived Role', 'slug' => 'archived-role']);

        $query = new RoleListQuery;
        $paginator = $query->archived('');

        $this->assertSame(1, $paginator->total());
        $this->assertSame('Archived Role', $paginator->first()->name);
    }

    public function test_archived_with_search_includes_updated_at(): void
    {
        $this->actingAs(User::factory()->withPermissions(['roles.view'])->create());
        Role::factory()->archived()->create(['name' => 'Archived Role', 'slug' => 'archived-role']);

        $query = new RoleListQuery;
        $paginator = $query->archived('archived-role');

        $this->assertSame(1, $paginator->total());
    }

    public function test_trashed_returns_trashed_roles(): void
    {
        $this->actingAs(User::factory()->withPermissions(['roles.view'])->create());
        Role::factory()->create(['name' => 'Active Role', 'slug' => 'active-role']);
        Role::factory()->trashed()->create(['name' => 'Trashed Role', 'slug' => 'trashed-role']);

        $query = new RoleListQuery;
        $paginator = $query->trashed('');

        $this->assertSame(1, $paginator->total());
        $this->assertSame('Trashed Role', $paginator->first()->name);
    }

    public function test_trashed_with_search_includes_deleted_at(): void
    {
        $this->actingAs(User::factory()->withPermissions(['roles.view'])->create());
        Role::factory()->trashed()->create(['name' => 'Trashed Role', 'slug' => 'trashed-role']);

        $query = new RoleListQuery;
        $paginator = $query->trashed('trashed-role');

        $this->assertSame(1, $paginator->total());
    }

    public function test_state_counts_are_correct(): void
    {
        $this->actingAs(User::factory()->withPermissions(['roles.view'])->create());
        $initialActive = Role::query()->where('active', true)->whereNull('deleted_at')->count();
        $initialArchived = Role::query()->where('active', false)->whereNull('deleted_at')->count();
        $initialTrashed = Role::onlyTrashed()->count();

        Role::factory()->count(3)->create();
        Role::factory()->count(2)->archived()->create();
        Role::factory()->count(1)->trashed()->create();

        $query = new RoleListQuery;
        $paginator = $query->active('');

        $counts = $query->stateCounts(activeTotal: $paginator->total());

        $this->assertSame($initialActive + 3, $counts['active']);
        $this->assertSame($initialArchived + 2, $counts['archived']);
        $this->assertSame($initialTrashed + 1, $counts['trashed']);
    }

    public function test_listed_includes_permissions_and_users_count(): void
    {
        $this->actingAs(User::factory()->withPermissions(['roles.view'])->create());
        $role = Role::factory()->create(['name' => 'Test Role']);
        User::factory()->count(2)->create()->each->assignRole($role);

        $query = new RoleListQuery;
        $paginator = $query->active('');

        $roleFromQuery = $paginator->firstWhere('name', 'Test Role');
        $this->assertNotNull($roleFromQuery);
        $this->assertSame(2, $roleFromQuery->users_count);
        $this->assertSame(0, $roleFromQuery->permissions_count); // no permissions assigned
    }

    public function test_searchable_fields_returns_empty(): void
    {
        $query = new RoleListQuery;

        $this->assertSame([], $query->searchableFields('active'));
        $this->assertSame([], $query->searchableFields('archived'));
        $this->assertSame([], $query->searchableFields('trashed'));
    }

    public function test_integration_search_columns_returns_base_columns(): void
    {
        $query = new RoleListQuery;

        // The integration's searchColumns method should return the columns unchanged
        // We test this indirectly through the search columns used in active/archived/trashed
        $reflection = new \ReflectionClass($query);
        $method = $reflection->getMethod('integration');
        $method->setAccessible(true);
        $integration = $method->invoke($query);

        $baseColumns = ['BAS_roles.name', 'BAS_roles.slug', 'BAS_roles.description'];
        $result = $integration->searchColumns('active', $baseColumns);

        $this->assertSame($baseColumns, $result);
    }
}
