<?php

namespace Basics13\Tests\Unit\Queries\Users;

use Basics13\Models\User;
use Basics13\Tests\TestCase;
use Basics13\Queries\Users\UserListQuery;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The query runs active(), archived(), and trashed() through the controller.
 * Those methods call paginate(), which in turn calls whereMatches() and applyFilters()
 * from the base class. We reach the required hooks the same way the base does.
 */
final class UserListQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('auth.providers.users.model', User::class);
    }

    public function test_it_reads_the_configured_account_model(): void
    {
        $this->assertSame(User::class, $this->invoke(new UserListQuery, 'getModelClass'));
    }

    public function test_the_integration_contributes_no_extra_search_columns(): void
    {
        $columns = $this->invoke(new UserListQuery, 'searchColumns', ['active', ['name', 'email']]);

        $this->assertSame(['name', 'email'], $columns);
    }

    public function test_searchable_fields_returns_correct_structure_for_active_scope(): void
    {
        $query = new UserListQuery;
        $fields = $query->searchableFields('active');

        $this->assertArrayHasKey('name', $fields);
        $this->assertArrayHasKey('email', $fields);
        $this->assertArrayHasKey('active', $fields);
        $this->assertArrayHasKey('created_at', $fields);

        $this->assertEquals('text', $fields['name']['type']);
        $this->assertEquals('text', $fields['email']['type']);
        $this->assertEquals('boolean', $fields['active']['type']);
        $this->assertEquals('date', $fields['created_at']['type']);
    }

    public function test_searchable_fields_returns_correct_structure_for_archived_scope(): void
    {
        $query = new UserListQuery;
        $fields = $query->searchableFields('archived');

        $this->assertArrayHasKey('name', $fields);
        $this->assertArrayHasKey('email', $fields);
        $this->assertArrayHasKey('active', $fields);
        $this->assertArrayHasKey('created_at', $fields);
    }

    public function test_searchable_fields_returns_correct_structure_for_trashed_scope(): void
    {
        $query = new UserListQuery;
        $fields = $query->searchableFields('trashed');

        $this->assertArrayHasKey('name', $fields);
        $this->assertArrayHasKey('email', $fields);
        $this->assertArrayHasKey('active', $fields);
        $this->assertArrayHasKey('created_at', $fields);
    }

    public function test_active_returns_paginator_with_correct_query(): void
    {
        User::factory()->create(['name' => 'Active User', 'email' => 'active@example.com', 'active' => true]);
        User::factory()->create(['name' => 'Inactive User', 'email' => 'inactive@example.com', 'active' => false]);

        $query = new UserListQuery;
        $result = $query->active('');

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertSame(1, $result->total());
        $this->assertSame('Active User', $result->first()->getAttribute('name'));
    }

    public function test_archived_returns_paginator_with_correct_query(): void
    {
        User::factory()->create(['name' => 'Active User', 'email' => 'active@example.com', 'active' => true]);
        User::factory()->create(['name' => 'Inactive User', 'email' => 'inactive@example.com', 'active' => false]);

        $query = new UserListQuery;
        $result = $query->archived('');

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertSame(1, $result->total());
        $this->assertSame('Inactive User', $result->first()->getAttribute('name'));
    }

    public function test_trashed_returns_paginator_with_correct_query(): void
    {
        $activeUser = User::factory()->create(['name' => 'Active User', 'email' => 'active@example.com', 'active' => true]);
        $inactiveUser = User::factory()->create(['name' => 'Inactive User', 'email' => 'inactive@example.com', 'active' => false]);

        $activeUser->delete();
        $inactiveUser->delete();

        $query = new UserListQuery;
        $result = $query->trashed('');

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertSame(2, $result->total());
    }

    public function test_active_searches_by_name_and_email(): void
    {
        User::factory()->create(['name' => 'Juan Perez', 'email' => 'juan@example.com', 'active' => true]);
        User::factory()->create(['name' => 'Maria Garcia', 'email' => 'maria@example.com', 'active' => true]);
        User::factory()->create(['name' => 'Pedro Lopez', 'email' => 'pedro@example.com', 'active' => true]);

        $query = new UserListQuery;
        $result = $query->active('Mar');

        $this->assertSame(1, $result->total());
        $this->assertSame('Maria Garcia', $result->first()->getAttribute('name'));
    }

    public function test_archived_searches_by_name_email_and_updated_at(): void
    {
        User::factory()->create(['name' => 'Juan Perez', 'email' => 'juan@example.com', 'active' => false]);
        User::factory()->create(['name' => 'Maria Garcia', 'email' => 'maria@example.com', 'active' => false]);

        $query = new UserListQuery;
        $result = $query->archived('Garcia');

        $this->assertSame(1, $result->total());
        $this->assertSame('Maria Garcia', $result->first()->getAttribute('name'));
    }

    public function test_trashed_searches_by_name_email_and_deleted_at(): void
    {
        $user1 = User::factory()->create(['name' => 'Juan Perez', 'email' => 'juan@example.com', 'active' => true]);
        $user2 = User::factory()->create(['name' => 'Maria Garcia', 'email' => 'maria@example.com', 'active' => true]);

        $user1->delete();
        $user2->delete();

        $query = new UserListQuery;
        $result = $query->trashed('Perez');

        $this->assertSame(1, $result->total());
        $this->assertSame('Juan Perez', $result->first()->getAttribute('name'));
    }

    public function test_active_applies_filters(): void
    {
        User::factory()->create(['name' => 'Juan', 'email' => 'juan@example.com', 'active' => true, 'created_at' => '2024-01-15']);
        User::factory()->create(['name' => 'Maria', 'email' => 'maria@example.com', 'active' => true, 'created_at' => '2024-02-15']);
        User::factory()->create(['name' => 'Pedro', 'email' => 'pedro@example.com', 'active' => true, 'created_at' => '2024-03-15']);

        $query = new UserListQuery;
        $result = $query->active('', [
            'active' => ['operator' => 'is_true', 'value' => true],
            'created_at' => ['operator' => 'between', 'value' => ['2024-01-01', '2024-02-28']],
        ]);

        $this->assertSame(2, $result->total());
    }

    public function test_ordered_returns_users_ordered_by_name_then_id(): void
    {
        $user3 = User::factory()->create(['name' => 'Carlos', 'email' => 'carlos@example.com', 'active' => true]);
        $user1 = User::factory()->create(['name' => 'Ana', 'email' => 'ana@example.com', 'active' => true]);
        $user2 = User::factory()->create(['name' => 'Ana', 'email' => 'ana2@example.com', 'active' => true]);

        $query = new UserListQuery;
        $builder = $this->invoke($query, 'ordered');

        $results = $builder->get();

        $this->assertSame('Ana', $results[0]->getAttribute('name'));
        $this->assertSame('Ana', $results[1]->getAttribute('name'));
        $this->assertSame('Carlos', $results[2]->getAttribute('name'));
        // Same name ordered by id
        $this->assertLessThan($results[1]->id, $results[0]->id ? $results[0]->id : $results[1]->id);
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    private function invoke(UserListQuery $query, string $method, array $arguments = []): mixed
    {
        $reflection = new \ReflectionMethod($query, $method);

        return $reflection->invoke($query, ...$arguments);
    }
}
