<?php

namespace Basics13\Tests\Unit\Queries;

use Basics13\Tests\TestCase;
use Basics13\Queries\ListQueryBase;
use Illuminate\Database\Eloquent\Builder;
use Basics13\Tests\Fixtures\AuditedRecord;
use Basics13\Tests\Fixtures\TestListQuery;
use Illuminate\Pagination\LengthAwarePaginator;
use Basics13\Tests\Fixtures\TestListQueryWithCondition;
use Basics13\Tests\Fixtures\TestListQueryWithVisibleFlags;
use Basics13\Tests\Fixtures\TestListQueryWithSearchColumns;
use Basics13\Tests\Fixtures\TestListQueryWithSelectOptions;
use Basics13\Tests\Fixtures\TestListQueryWithGroupsAndOrder;
use Basics13\Tests\Fixtures\TestListQueryWithSelectOptionsMultiple;

/**
 * Test the abstract ListQueryBase through a concrete test subclass.
 *
 * @phpstan-import-type SearchFields from ListQueryBase
 * @phpstan-import-type SearchFilters from ListQueryBase
 */
final class ListQueryBaseTest extends TestCase
{
    private TestListQuery $query;

    protected function setUp(): void
    {
        parent::setUp();
        $this->query = new TestListQuery;
    }

    public function test_per_page_constant_is_15(): void
    {
        $this->assertSame(15, ListQueryBase::PER_PAGE);
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
            ->with(15)
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

    // =========================================================================
    // Advanced Search: searchableFields() structure and defaults
    // =========================================================================

    public function test_searchable_fields_returns_correct_structure_with_all_keys(): void
    {
        $fields = $this->query->searchableFields('active');

        $this->assertArrayHasKey('name', $fields);
        $field = $fields['name'];

        $this->assertArrayHasKey('column', $field);
        $this->assertArrayHasKey('label', $field);
        $this->assertArrayHasKey('type', $field);
        $this->assertArrayHasKey('operators', $field);
        $this->assertArrayHasKey('default_operator', $field);
        $this->assertArrayHasKey('visible', $field);
        $this->assertArrayHasKey('order', $field);
        $this->assertArrayHasKey('group', $field);
    }

    public function test_searchable_fields_applies_type_aware_default_operators(): void
    {
        $fields = $this->query->searchableFields('active');

        // Text field should get default operators
        $textField = $fields['name'];
        $this->assertEquals('text', $textField['type']);
        $this->assertContains('contains', $textField['operators'] ?? []);
        $this->assertEquals('contains', $textField['default_operator'] ?? null);
    }

    public function test_searchable_fields_groups_and_orders_fields(): void
    {
        $query = new TestListQueryWithGroupsAndOrder;
        $fields = $query->searchableFields('active');

        // Get fields in order they should appear
        $fieldOrder = array_keys($fields);

        // Should be ordered by group, then by order
        $expectedOrder = [
            'name',        // group=basic, order=10
            'description', // group=basic, order=20
            'active',      // group=status, order=10
            'created_at',  // group=dates, order=10
        ];

        $this->assertEquals($expectedOrder, $fieldOrder);
    }

    public function test_searchable_fields_respects_visibility_and_advanced_toggle(): void
    {
        $query = new TestListQueryWithVisibleFlags;
        $fields = $query->searchableFields('active');

        // The searchableFields method returns ALL fields; visibility is handled by the component
        // Verify that 'notes' has visible=false
        $this->assertArrayHasKey('notes', $fields);
        $this->assertFalse($fields['notes']['visible'] ?? true);
        $this->assertArrayHasKey('name', $fields);
    }

    public function test_searchable_fields_respects_condition_callable(): void
    {
        $query = new TestListQueryWithCondition;
        $fields = $query->searchableFields('active');

        // The searchableFields method returns ALL fields; condition is evaluated by the component
        $this->assertArrayHasKey('entity_id', $fields);
        $this->assertArrayHasKey('condition', $fields['entity_id']);

        $condition = $fields['entity_id']['condition'] ?? null;
        $this->assertIsCallable($condition);

        $this->assertFalse($condition('active'));
        $this->assertTrue($condition('archived'));
    }

    public function test_searchable_fields_includes_select_options_callable(): void
    {
        $query = new TestListQueryWithSelectOptions;
        $fields = $query->searchableFields('active');

        $this->assertArrayHasKey('entity_id', $fields);
        $field = $fields['entity_id'];

        $this->assertEquals('select', $field['type']);
        $this->assertArrayHasKey('options', $field);

        $options = $field['options'] ?? null;
        $this->assertIsCallable($options);
        $this->assertSame([1 => 'Entity One', 2 => 'Entity Two'], $options('active'));
    }

    public function test_searchable_fields_select_defaults_to_multiple_true(): void
    {
        $query = new TestListQueryWithSelectOptionsMultiple;
        $fields = $query->searchableFields('active');

        $field = $fields['entity_id'];
        $this->assertTrue($field['multiple'] ?? false);
    }

    public function test_apply_filters_number_between_applies_both_bounds(): void
    {
        $builder = $this->createMock(Builder::class);
        /** @var list<array<int, mixed>> $whereCalls */
        $whereCalls = [];

        $builder->expects($this->exactly(2))
            ->method('where')
            ->willReturnCallback(function (...$arguments) use (&$whereCalls, $builder): Builder {
                $whereCalls[] = $arguments;

                return $builder;
            });

        $this->callApplyFilters(
            $builder,
            ['priority' => ['operator' => 'between', 'value' => [10, 20]]],
            ['priority' => ['column' => 'priority', 'label' => 'test.priority', 'type' => 'number']],
        );

        $this->assertSame([
            ['priority', '>=', 10, 'and'],
            ['priority', '<=', 20, 'and'],
        ], $whereCalls);
    }

    // =========================================================================
    // escapeLikeValue
    // =========================================================================

    public function test_escape_like_value_escapes_percent(): void
    {
        $result = $this->callEscapeLikeValue('100%');

        $this->assertSame('100!%', $result);
    }

    public function test_escape_like_value_escapes_underscore(): void
    {
        $result = $this->callEscapeLikeValue('a_b');

        $this->assertSame('a!_b', $result);
    }

    public function test_escape_like_value_escapes_escape_character(): void
    {
        $result = $this->callEscapeLikeValue('!test');

        $this->assertSame('!!test', $result);
    }

    public function test_escape_like_value_escapes_multiple(): void
    {
        $result = $this->callEscapeLikeValue('a%_b!c');

        $this->assertSame('a!%!_b!!c', $result);
    }

    public function test_escape_like_value_no_special_chars(): void
    {
        $result = $this->callEscapeLikeValue('normal');

        $this->assertSame('normal', $result);
    }

    // =========================================================================
    // getDefaultOperatorsForType static method
    // =========================================================================

    public function test_get_default_operators_for_type_text(): void
    {
        $operators = ListQueryBase::getDefaultOperatorsForType('text');
        $this->assertSame(['contains', 'equals', 'not_equals', 'starts_with', 'ends_with'], $operators);
    }

    public function test_get_default_operators_for_type_date_nullable(): void
    {
        $operators = ListQueryBase::getDefaultOperatorsForType('date', true);
        $this->assertSame(['equals', 'before', 'after', 'between', 'is_empty'], $operators);
    }

    public function test_get_default_operators_for_type_date_not_nullable(): void
    {
        $operators = ListQueryBase::getDefaultOperatorsForType('date', false);
        $this->assertSame(['equals', 'before', 'after', 'between'], $operators);
    }

    public function test_get_default_operators_for_type_boolean(): void
    {
        $operators = ListQueryBase::getDefaultOperatorsForType('boolean');
        $this->assertSame(['is_true', 'is_false'], $operators);
    }

    public function test_get_default_operators_for_type_number(): void
    {
        $operators = ListQueryBase::getDefaultOperatorsForType('number');
        $this->assertSame(['equals', 'not_equals', 'greater_than', 'greater_than_or_equal', 'less_than', 'less_than_or_equal', 'between'], $operators);
    }

    public function test_get_default_operators_for_type_select_nullable(): void
    {
        $operators = ListQueryBase::getDefaultOperatorsForType('select', true);
        $this->assertSame(['equals', 'not_equals', 'is_empty'], $operators);
    }

    public function test_get_default_operators_for_type_select_not_nullable(): void
    {
        $operators = ListQueryBase::getDefaultOperatorsForType('select', false);
        $this->assertSame(['equals', 'not_equals'], $operators);
    }

    public function test_get_default_operators_for_type_unknown(): void
    {
        $operators = ListQueryBase::getDefaultOperatorsForType('unknown');
        $this->assertSame(['equals', 'not_equals'], $operators);
    }

    // =========================================================================
    // getDefaultOperatorForType static method
    // =========================================================================

    public function test_get_default_operator_for_type_text(): void
    {
        $operator = ListQueryBase::getDefaultOperatorForType('text');
        $this->assertSame('contains', $operator);
    }

    public function test_get_default_operator_for_type_date(): void
    {
        $operator = ListQueryBase::getDefaultOperatorForType('date');
        $this->assertSame('between', $operator);
    }

    public function test_get_default_operator_for_type_boolean(): void
    {
        $operator = ListQueryBase::getDefaultOperatorForType('boolean');
        $this->assertSame('', $operator);
    }

    public function test_get_default_operator_for_type_number(): void
    {
        $operator = ListQueryBase::getDefaultOperatorForType('number');
        $this->assertSame('equals', $operator);
    }

    public function test_get_default_operator_for_type_select(): void
    {
        $operator = ListQueryBase::getDefaultOperatorForType('select');
        $this->assertSame('equals', $operator);
    }

    public function test_get_default_operator_for_type_unknown(): void
    {
        $operator = ListQueryBase::getDefaultOperatorForType('unknown');
        $this->assertSame('equals', $operator);
    }

    // =========================================================================
    // Helper methods for calling private/protected methods
    // =========================================================================

    private function callSearchPattern(string $search): string
    {
        $reflection = new \ReflectionMethod($this->query, 'searchPattern');

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

        /** @var Builder<AuditedRecord> */
        return $reflection->invoke($this->query, $builder, $search, $searchColumns);
    }

    /**
     * @param  Builder<AuditedRecord>  $builder
     */
    private function callFirstTrashedByName(Builder $builder, string $name): ?AuditedRecord
    {
        $reflection = new \ReflectionMethod($this->query, 'firstTrashedByName');

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

        /** @var LengthAwarePaginator<int, AuditedRecord> */
        return $reflection->invoke($this->query, $builder, $search, $searchColumns);
    }

    /**
     * @param  Builder<AuditedRecord>  $builder
     * @param  SearchFilters  $filters
     * @param  SearchFields  $fields
     */
    private function callApplyFilters(Builder $builder, array $filters, array $fields): void
    {
        $reflection = new \ReflectionMethod($this->query, 'applyFilters');
        $reflection->invoke($this->query, $builder, $filters, $fields, 'active');
    }

    /**
     * @return array{active: int, archived: int, trashed: int}
     */
    private function callStateCounts(TestListQuery $query, ?int $activeTotal = null, ?int $archivedTotal = null, ?int $trashedTotal = null): array
    {
        $reflection = new \ReflectionMethod($query, 'stateCounts');

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

        /** @var non-empty-list<literal-string> */
        return $reflection->invoke($query, $scope, $columns);
    }

    private function callEscapeLikeValue(string $value): string
    {
        $reflection = new \ReflectionMethod($this->query, 'escapeLikeValue');

        /** @var string */
        return $reflection->invoke($this->query, $value);
    }

    public function test_state_counts_accepts_precomputed_totals(): void
    {
        $query = new TestListQuery;
        $counts = $query->stateCounts(10, 5, 2);

        $this->assertSame(10, $counts['active']);
        $this->assertSame(5, $counts['archived']);
        $this->assertSame(2, $counts['trashed']);
    }
}
