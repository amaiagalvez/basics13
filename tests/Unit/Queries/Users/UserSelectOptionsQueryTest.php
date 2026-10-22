<?php

namespace Basics13\Tests\Unit\Queries\Users;

use Basics13\Models\User;
use Basics13\Tests\TestCase;
use Basics13\Queries\Users\UserSelectOptionsQuery;

/**
 * The query only ever runs `search()` through the controller, so its two required ListQueryBase
 * hooks are reached here the way the base class calls them.
 */
final class UserSelectOptionsQueryTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('auth.providers.users.model', User::class);
    }

    public function test_it_reads_the_configured_account_model(): void
    {
        $this->assertSame(User::class, $this->invoke(new UserSelectOptionsQuery, 'getModelClass'));
    }

    public function test_the_integration_contributes_no_extra_search_columns(): void
    {
        $columns = $this->invoke(new UserSelectOptionsQuery, 'searchColumns', ['active', ['name', 'email']]);

        $this->assertSame(['name', 'email'], $columns);
    }

    public function test_searchable_fields_returns_correct_structure(): void
    {
        $query = new UserSelectOptionsQuery;
        $fields = $query->searchableFields('active');

        $this->assertArrayHasKey('name', $fields);
        $this->assertArrayHasKey('email', $fields);

        $this->assertEquals('text', $fields['name']['type']);
        $this->assertEquals('text', $fields['email']['type']);
        $this->assertContains('contains', $fields['name']['operators'] ?? []);
        $this->assertContains('starts_with', $fields['name']['operators'] ?? []);
        $this->assertContains('ends_with', $fields['name']['operators'] ?? []);
        $this->assertEquals('contains', $fields['name']['default_operator'] ?? null);
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    private function invoke(UserSelectOptionsQuery $query, string $method, array $arguments = []): mixed
    {
        $reflection = new \ReflectionMethod($query, $method);

        return $reflection->invoke($query, ...$arguments);
    }
}
