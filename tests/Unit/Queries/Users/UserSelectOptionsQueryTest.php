<?php

namespace Basics13\Tests\Unit\Queries\Users;

use Basics13\Models\User;
use Basics13\Tests\TestCase;
use Illuminate\Support\Collection;
use Basics13\Queries\Users\UserSelectOptionsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The query only ever runs `search()` through the controller, so its two required ListQueryBase
 * hooks are reached here the way the base class calls them.
 */
final class UserSelectOptionsQueryTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_search_returns_empty_collection_when_no_users(): void
    {
        $query = new UserSelectOptionsQuery;
        $result = $query->search('');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertSame(0, $result->count());
    }

    public function test_search_returns_only_active_users_as_id_and_text(): void
    {
        $active = User::factory()->count(3)->create(['active' => true]);
        User::factory()->count(2)->create(['active' => false]);

        $result = (new UserSelectOptionsQuery)->search('');

        $this->assertEqualsCanonicalizing($active->modelKeys(), $result->pluck('id')->all());
        $this->assertSame(['id', 'text'], array_keys($result->first()));
    }

    public function test_search_filters_by_name_and_email(): void
    {
        User::factory()->create(['name' => 'Juan Perez', 'email' => 'juan@example.com', 'active' => true]);
        User::factory()->create(['name' => 'Maria Garcia', 'email' => 'maria@example.com', 'active' => true]);
        User::factory()->create(['name' => 'Pedro Lopez', 'email' => 'pedro@example.com', 'active' => true]);

        $query = new UserSelectOptionsQuery;
        $result = $query->search('Mar');

        $this->assertSame(1, $result->count());
        $this->assertSame('Maria Garcia', $result->first()['text']);
    }

    public function test_search_orders_by_name_then_id(): void
    {
        $user3 = User::factory()->create(['name' => 'Carlos', 'email' => 'carlos@example.com', 'active' => true]);
        $user1 = User::factory()->create(['name' => 'Ana', 'email' => 'ana@example.com', 'active' => true]);
        $user2 = User::factory()->create(['name' => 'Ana', 'email' => 'ana2@example.com', 'active' => true]);

        $result = (new UserSelectOptionsQuery)->search('');

        $this->assertSame([$user1->id, $user2->id, $user3->id], $result->pluck('id')->all());
    }

    public function test_search_escapes_special_characters(): void
    {
        User::factory()->create(['name' => '100% Real', 'email' => 'real@example.com', 'active' => true]);
        User::factory()->create(['name' => 'Normal User', 'email' => 'normal@example.com', 'active' => true]);

        $query = new UserSelectOptionsQuery;
        $result = $query->search('100%');

        $this->assertSame(1, $result->count());
        $this->assertSame('100% Real', $result->first()['text']);
    }

    public function test_search_respects_per_page_limit(): void
    {
        User::factory()->count(50)->create(['active' => true]);

        $query = new UserSelectOptionsQuery;
        $result = $query->search('');

        $this->assertSame(UserSelectOptionsQuery::PER_PAGE, $result->count());
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
