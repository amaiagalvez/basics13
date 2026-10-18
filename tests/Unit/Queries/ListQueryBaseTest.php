<?php

namespace Basics13\Tests\Unit\Queries;

use PHPUnit\Framework\TestCase;
use Basics13\Queries\ListQueryBase;
use Illuminate\Database\Eloquent\Builder;
use Basics13\Tests\Fixtures\AuditedRecord;
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

    public function test_state_counts_uses_provided_totals(): void
    {
        $result = $this->callStateCounts($this->query, 10, 5, 2);

        $this->assertSame([
            'active' => 10,
            'archived' => 5,
            'trashed' => 2,
        ], $result);
    }

    public function test_state_counts_passes_through_provided_totals(): void
    {
        $query = new TestListQuery;
        $result = $this->callStateCounts($query, 7, 3, 1);

        $this->assertSame([
            'active' => 7,
            'archived' => 3,
            'trashed' => 1,
        ], $result);
    }

    public function test_search_columns_calls_integration_search_columns(): void
    {
        $query = new TestListQueryWithSearchColumns;
        $result = $this->callSearchColumns($query, 'entities', ['name', 'email']);

        $this->assertSame(['name', 'email', 'integration-column'], $result);
    }

    private function callSearchPattern(string $search): string
    {
        $reflection = new \ReflectionMethod($this->query, 'searchPattern');
        $reflection->setAccessible(true);

        /** @var string */
        return $reflection->invoke($this->query, $search);
    }

    /**
     * @param  Builder<AuditedRecord>  $builder
     * @param  array<int, string>  $searchColumns
     * @return Builder<AuditedRecord>
     */
    private function callWhereMatches(Builder $builder, string $search, array $searchColumns): Builder
    {
        $reflection = new \ReflectionMethod($this->query, 'whereMatches');
        $reflection->setAccessible(true);

        /** @var Builder<AuditedRecord> */
        return $reflection->invoke($this->query, $builder, $search, $searchColumns);
    }

    /**
     * @param  Builder<AuditedRecord>  $builder
     */
    private function callFirstTrashedByName(Builder $builder, string $name): ?AuditedRecord
    {
        $reflection = new \ReflectionMethod($this->query, 'firstTrashedByName');
        $reflection->setAccessible(true);

        /** @var AuditedRecord|null */
        return $reflection->invoke($this->query, $builder, $name);
    }

    /**
     * @param  Builder<AuditedRecord>  $builder
     * @param  array<int, string>  $searchColumns
     * @return LengthAwarePaginator<int, AuditedRecord>
     */
    private function callPaginate(Builder $builder, string $search, array $searchColumns): LengthAwarePaginator
    {
        $reflection = new \ReflectionMethod($this->query, 'paginate');
        $reflection->setAccessible(true);

        /** @var LengthAwarePaginator<int, AuditedRecord> */
        return $reflection->invoke($this->query, $builder, $search, $searchColumns);
    }

    /**
     * @return array{active: int, archived: int, trashed: int}
     */
    private function callStateCounts(TestListQuery $query, ?int $activeTotal = null, ?int $archivedTotal = null, ?int $trashedTotal = null): array
    {
        $reflection = new \ReflectionMethod($query, 'stateCounts');
        $reflection->setAccessible(true);

        /** @var array{active: int, archived: int, trashed: int} */
        return $reflection->invoke($query, $activeTotal, $archivedTotal, $trashedTotal);
    }

    /**
     * @param  non-empty-list<literal-string>  $columns
     * @return non-empty-list<literal-string>
     */
    private function callSearchColumns(TestListQueryWithSearchColumns $query, string $scope, array $columns): array
    {
        $reflection = new \ReflectionMethod($query, 'searchColumns');
        $reflection->setAccessible(true);

        /** @var non-empty-list<literal-string> */
        return $reflection->invoke($query, $scope, $columns);
    }
}

/**
 * Concrete implementation of ListQueryBase for testing.
 *
 * @extends ListQueryBase<AuditedRecord>
 */
class TestListQuery extends ListQueryBase
{
    /**
     * @return class-string<AuditedRecord>
     */
    protected function getModelClass(): string
    {
        return AuditedRecord::class;
    }

    protected function integration(): object
    {
        return new class
        {
            /**
             * @param  non-empty-list<literal-string>  $columns
             * @return non-empty-list<literal-string>
             */
            public function searchColumns(string $scope, array $columns): array
            {
                return $columns;
            }
        };
    }
}

/**
 * Test implementation that tracks searchColumns calls.
 *
 * @extends ListQueryBase<AuditedRecord>
 */
class TestListQueryWithSearchColumns extends ListQueryBase
{
    /**
     * @return class-string<AuditedRecord>
     */
    protected function getModelClass(): string
    {
        return AuditedRecord::class;
    }

    protected function integration(): object
    {
        return new class
        {
            /**
             * @param  non-empty-list<literal-string>  $columns
             * @return non-empty-list<literal-string>
             */
            public function searchColumns(string $scope, array $columns): array
            {
                return [...$columns, 'integration-column'];
            }
        };
    }
}
