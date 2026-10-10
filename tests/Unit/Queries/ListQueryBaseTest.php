<?php

namespace Basics13\Tests\Unit\Queries;

use PHPUnit\Framework\TestCase;
use Basics13\Queries\ListQueryBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Test the abstract ListQueryBase through a concrete test subclass.
 */
final class ListQueryBaseTest extends TestCase
{
    private TestListQuery $query;

    protected function setUp(): void
    {
        parent::setUp();
        $this->query = new TestListQuery;
    }

    public function test_per_page_constant_is_25(): void
    {
        $this->assertSame(25, ListQueryBase::PER_PAGE);
    }

    public function test_like_escape_constant_is_exclamation_mark(): void
    {
        $reflection = new \ReflectionClass(ListQueryBase::class);
        $constant = $reflection->getConstant('LIKE_ESCAPE');
        $this->assertSame('!', $constant);
    }

    public function test_search_pattern_escapes_percent(): void
    {
        $result = $this->callSearchPattern('100%');
        $this->assertSame('%100!%%', $result);
    }

    public function test_search_pattern_escapes_underscore(): void
    {
        $result = $this->callSearchPattern('a_b');
        $this->assertSame('%a!_b%', $result);
    }

    public function test_search_pattern_escapes_escape_character(): void
    {
        $result = $this->callSearchPattern('!test');
        $this->assertSame('%!!test%', $result);
    }

    public function test_search_pattern_wraps_with_wildcards(): void
    {
        $result = $this->callSearchPattern('search');
        $this->assertSame('%search%', $result);
    }

    public function test_search_pattern_handles_multiple_special_chars(): void
    {
        $result = $this->callSearchPattern('a%_b!c');
        $this->assertSame('%a!%!_b!!c%', $result);
    }

    public function test_search_pattern_empty_string_returns_wildcards_only(): void
    {
        $result = $this->callSearchPattern('');
        $this->assertSame('%%', $result);
    }

    public function test_where_matches_returns_same_query_when_search_is_empty(): void
    {
        $builder = $this->createMock(Builder::class);
        $builder->expects($this->never())->method('where');

        $result = $this->callWhereMatches($builder, '', ['name']);

        $this->assertSame($builder, $result);
    }

    public function test_where_matches_adds_where_clause_when_search_is_provided(): void
    {
        $builder = $this->createMock(Builder::class);
        $builder->expects($this->once())
            ->method('where')
            ->willReturn($builder);

        $result = $this->callWhereMatches($builder, 'test', ['name']);

        $this->assertSame($builder, $result);
    }

    public function test_where_matches_adds_or_where_for_multiple_columns(): void
    {
        $builder = $this->createMock(Builder::class);
        $builder->expects($this->once())
            ->method('where')
            ->willReturn($builder);

        $result = $this->callWhereMatches($builder, 'test', ['name', 'email', 'notes']);

        $this->assertSame($builder, $result);
    }

    public function test_first_trashed_by_name_orders_by_deleted_at(): void
    {
        $builder = $this->createMock(Builder::class);
        $builder->expects($this->once())
            ->method('where')
            ->with('name', 'test')
            ->willReturnSelf();
        $builder->expects($this->once())
            ->method('latest')
            ->with('deleted_at')
            ->willReturnSelf();
        $builder->expects($this->once())
            ->method('first')
            ->willReturn(null);

        $model = $this->callFirstTrashedByName($builder, 'test');

        $this->assertNull($model);
    }

    public function test_paginate_calls_where_matches_and_paginates(): void
    {
        $builder = $this->createMock(Builder::class);
        $builder->expects($this->once())
            ->method('where')
            ->with($this->isInstanceOf(\Closure::class))
            ->willReturnSelf();
        $stubPaginator = $this->createStub(LengthAwarePaginator::class);
        $builder->expects($this->once())
            ->method('paginate')
            ->with(25)
            ->willReturn($stubPaginator);

        $paginator = $this->callPaginate($builder, 'test', ['name']);

        $this->assertSame($stubPaginator, $paginator);
    }

    public function test_paginate_appends_search_when_not_empty(): void
    {
        $builder = $this->createStub(Builder::class);
        $builder->method('where')->willReturn($builder);
        $mockPaginator = $this->createMock(LengthAwarePaginator::class);
        $mockPaginator->expects($this->once())
            ->method('appends')
            ->with(['search' => 'test']);
        $builder->method('paginate')->willReturn($mockPaginator);

        $this->callPaginate($builder, 'test', ['name']);
    }

    public function test_paginate_does_not_append_when_empty(): void
    {
        $builder = $this->createStub(Builder::class);
        $mockPaginator = $this->createMock(LengthAwarePaginator::class);
        $mockPaginator->expects($this->never())
            ->method('appends');
        $builder->method('paginate')->willReturn($mockPaginator);

        $this->callPaginate($builder, '', ['name']);
    }

    public function test_count_states_uses_provided_totals(): void
    {
        $result = $this->callCountStates('stdClass', 10, 5, 2);

        $this->assertSame([
            'active' => 10,
            'archived' => 5,
            'trashed' => 2,
        ], $result);
    }

    public function test_count_states_method_exists_and_is_protected(): void
    {
        $reflection = new \ReflectionMethod(ListQueryBase::class, 'countStates');
        $this->assertTrue($reflection->isProtected());
        $this->assertEquals(4, $reflection->getNumberOfParameters());
    }

    private function callSearchPattern(string $search): string
    {
        $reflection = new \ReflectionMethod($this->query, 'searchPattern');
        $reflection->setAccessible(true);

        /** @var string */
        return $reflection->invoke($this->query, $search);
    }

    /**
     * @param  Builder<Model>  $builder
     * @param  array<int, string>  $searchColumns
     * @return Builder<Model>
     */
    private function callWhereMatches(Builder $builder, string $search, array $searchColumns): Builder
    {
        $reflection = new \ReflectionMethod($this->query, 'whereMatches');
        $reflection->setAccessible(true);

        /** @var Builder<Model> */
        return $reflection->invoke($this->query, $builder, $search, $searchColumns);
    }

    /**
     * @param  Builder<Model>  $builder
     */
    private function callFirstTrashedByName(Builder $builder, string $name): ?Model
    {
        $reflection = new \ReflectionMethod($this->query, 'firstTrashedByName');
        $reflection->setAccessible(true);

        /** @var Model|null */
        return $reflection->invoke($this->query, $builder, $name);
    }

    /**
     * @param  Builder<Model>  $builder
     * @param  array<int, string>  $searchColumns
     * @return LengthAwarePaginator<int, Model>
     */
    private function callPaginate(Builder $builder, string $search, array $searchColumns): LengthAwarePaginator
    {
        $reflection = new \ReflectionMethod($this->query, 'paginate');
        $reflection->setAccessible(true);

        /** @var LengthAwarePaginator<int, Model> */
        return $reflection->invoke($this->query, $builder, $search, $searchColumns);
    }

    /**
     * @return array{active: int, archived: int, trashed: int}
     */
    private function callCountStates(string $modelClass, ?int $activeTotal, ?int $archivedTotal, ?int $trashedTotal): array
    {
        $reflection = new \ReflectionMethod($this->query, 'countStates');
        $reflection->setAccessible(true);

        /** @var array{active: int, archived: int, trashed: int} */
        return $reflection->invoke($this->query, $modelClass, $activeTotal, $archivedTotal, $trashedTotal);
    }
}

/**
 * Concrete implementation of ListQueryBase for testing.
 *
 * @extends ListQueryBase<Model>
 */
class TestListQuery extends ListQueryBase
{
    // No additional methods needed for testing base functionality
}
