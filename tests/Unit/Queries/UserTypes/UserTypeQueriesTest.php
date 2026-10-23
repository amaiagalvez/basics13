<?php

namespace Basics13\Tests\Unit\Queries\UserTypes;

use Basics13\Tests\TestCase;
use Basics13\Models\UserType;
use Basics13\Queries\ListQueryBase;
use Basics13\Queries\UserTypes\UserTypeListQuery;
use Basics13\Queries\UserTypes\UserTypeSelectOptionsQuery;

/**
 * Both queries pass their columns straight to the base, so the required hooks are reached the
 * way the base reaches them.
 */
final class UserTypeQueriesTest extends TestCase
{
    public function test_the_list_query_reads_the_user_type_model_and_has_no_filters(): void
    {
        $query = new UserTypeListQuery;

        $this->assertSame(UserType::class, $this->invoke($query, 'getModelClass'));
        $this->assertSame([], $query->searchableFields('active'));
        $this->assertSame(['name_eu'], $this->invoke($query, 'searchColumns', ['active', ['name_eu']]));
    }

    public function test_the_select_options_query_reads_the_user_type_model_and_has_no_filters(): void
    {
        $query = new UserTypeSelectOptionsQuery;

        $this->assertSame(UserType::class, $this->invoke($query, 'getModelClass'));
        $this->assertSame([], $query->searchableFields('active'));
        $this->assertSame(['name_eu'], $this->invoke($query, 'searchColumns', ['active', ['name_eu']]));
    }

    /**
     * @param  ListQueryBase<UserType>  $query
     * @param  array<int, mixed>  $arguments
     */
    private function invoke(ListQueryBase $query, string $method, array $arguments = []): mixed
    {
        $reflection = new \ReflectionMethod($query, $method);

        return $reflection->invoke($query, ...$arguments);
    }
}
