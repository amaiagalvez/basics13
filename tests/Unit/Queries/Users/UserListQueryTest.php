<?php

namespace Basics13\Tests\Unit\Queries\Users;

use Basics13\Models\User;
use Basics13\Tests\TestCase;
use Basics13\Queries\Users\UserListQuery;

/**
 * The query runs active(), archived(), and trashed() through the controller.
 * Those methods call paginate(), which in turn calls whereMatches() and applyFilters()
 * from the base class. We reach the required hooks the same way the base does.
 */
final class UserListQueryTest extends TestCase
{
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
        $this->assertArrayNotHasKey('active', $fields);
        $this->assertArrayHasKey('created_at', $fields);

        $this->assertEquals('text', $fields['name']['type']);
        $this->assertEquals('text', $fields['email']['type']);
        $this->assertEquals('date', $fields['created_at']['type']);
    }

    public function test_searchable_fields_returns_correct_structure_for_archived_scope(): void
    {
        $query = new UserListQuery;
        $fields = $query->searchableFields('archived');

        $this->assertArrayHasKey('name', $fields);
        $this->assertArrayHasKey('email', $fields);
        $this->assertArrayNotHasKey('active', $fields);
        $this->assertArrayHasKey('created_at', $fields);
    }

    public function test_searchable_fields_returns_correct_structure_for_trashed_scope(): void
    {
        $query = new UserListQuery;
        $fields = $query->searchableFields('trashed');

        $this->assertArrayHasKey('name', $fields);
        $this->assertArrayHasKey('email', $fields);
        // The trash is the only list that mixes active and archived records.
        $this->assertSame('boolean', $fields['active']['type'] ?? null);
        $this->assertArrayHasKey('active', $fields);
        $this->assertArrayHasKey('created_at', $fields);
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
