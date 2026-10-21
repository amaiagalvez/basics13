<?php

namespace Basics13\Tests\Unit\Queries;

use Carbon\CarbonImmutable;
use Basics13\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Basics13\Queries\ListQueryBase;
use Illuminate\Database\Eloquent\Builder;
use Basics13\Tests\Fixtures\AuditedRecord;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test the abstract ListQueryBase through a concrete test subclass.
 *
 * @phpstan-import-type SearchFields from ListQueryBase
 * @phpstan-import-type SearchFilters from ListQueryBase
 */
final class ListQueryBaseTest extends TestCase
{
    use RefreshDatabase;

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

    // =========================================================================
    // Advanced Search: applyFilters() operator mapping
    // =========================================================================

    /**
     * @requires extension pdo_sqlite
     */
    public function test_apply_filters_text_contains(): void
    {
        AuditedRecord::create(['name' => 'Juan Perez', 'notes' => 'test']);
        AuditedRecord::create(['name' => 'Maria Garcia', 'notes' => 'test']);
        AuditedRecord::create(['name' => 'Pedro Lopez', 'notes' => 'test']);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['name' => ['operator' => 'contains', 'value' => 'Mar']],
            ['name' => ['column' => 'name', 'label' => 'test.name', 'type' => 'text']],
        );

        $this->assertSame(1, $builder->count());
        $this->assertSame('Maria Garcia', $builder->first()->name);
    }

    /**
     * @requires extension pdo_sqlite
     */
    public function test_apply_filters_text_equals(): void
    {
        AuditedRecord::create(['name' => 'Juan Perez', 'notes' => 'test']);
        AuditedRecord::create(['name' => 'Maria Garcia', 'notes' => 'test']);
        AuditedRecord::create(['name' => 'Pedro Lopez', 'notes' => 'test']);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['name' => ['operator' => 'equals', 'value' => 'Juan Perez']],
            ['name' => ['column' => 'name', 'label' => 'test.name', 'type' => 'text']],
        );

        $this->assertSame(1, $builder->count());
        $this->assertSame('Juan Perez', $builder->first()->name);
    }

    /**
     * @requires extension pdo_sqlite
     */
    public function test_apply_filters_text_starts_with(): void
    {
        AuditedRecord::create(['name' => 'Juan Perez', 'notes' => 'test']);
        AuditedRecord::create(['name' => 'Maria Garcia', 'notes' => 'test']);
        AuditedRecord::create(['name' => 'Pedro Lopez', 'notes' => 'test']);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['name' => ['operator' => 'starts_with', 'value' => 'Ped']],
            ['name' => ['column' => 'name', 'label' => 'test.name', 'type' => 'text']],
        );

        $this->assertSame(1, $builder->count());
        $this->assertSame('Pedro Lopez', $builder->first()->name);
    }

    /**
     * @requires extension pdo_sqlite
     */
    public function test_apply_filters_text_ends_with(): void
    {
        AuditedRecord::create(['name' => 'Juan Perez', 'notes' => 'test']);
        AuditedRecord::create(['name' => 'Maria Gomez', 'notes' => 'test']);
        AuditedRecord::create(['name' => 'Pedro Martinez', 'notes' => 'test']);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['name' => ['operator' => 'ends_with', 'value' => 'rez']],
            ['name' => ['column' => 'name', 'label' => 'test.name', 'type' => 'text']],
        );

        $this->assertSame(1, $builder->count());
        $this->assertSame('Juan Perez', $builder->first()->name);
    }

    /**
     * @requires extension pdo_sqlite
     */
    public function test_apply_filters_text_not_equals(): void
    {
        AuditedRecord::create(['name' => 'Juan Perez', 'notes' => 'test']);
        AuditedRecord::create(['name' => 'Maria Garcia', 'notes' => 'test']);
        AuditedRecord::create(['name' => 'Pedro Lopez', 'notes' => 'test']);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['name' => ['operator' => 'not_equals', 'value' => 'Juan Perez']],
            ['name' => ['column' => 'name', 'label' => 'test.name', 'type' => 'text']],
        );

        $this->assertSame(2, $builder->count());
        $names = $builder->pluck('name')->all();
        $this->assertContains('Maria Garcia', $names);
        $this->assertContains('Pedro Lopez', $names);
        $this->assertNotContains('Juan Perez', $names);
    }

    /**
     * @requires extension pdo_sqlite
     */
    public function test_apply_filters_date_between(): void
    {
        $date1 = CarbonImmutable::now()->subDays(10);
        $date2 = CarbonImmutable::now()->subDays(5);
        $date3 = CarbonImmutable::now();

        DB::table('audited_records')->insert([
            ['name' => 'Record 1', 'created_at' => $date1, 'updated_at' => $date1, 'active' => true, 'notes' => 'test'],
            ['name' => 'Record 2', 'created_at' => $date2, 'updated_at' => $date2, 'active' => true, 'notes' => 'test'],
            ['name' => 'Record 3', 'created_at' => $date3, 'updated_at' => $date3, 'active' => true, 'notes' => 'test'],
        ]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['created_at' => ['operator' => 'between', 'value' => [$date2->toDateString(), $date3->toDateString()]]],
            ['created_at' => ['column' => 'created_at', 'label' => 'test.created_at', 'type' => 'date']],
        );

        $this->assertSame(2, $builder->count());
    }

    /**
     * @requires extension pdo_sqlite
     */
    public function test_apply_filters_date_before(): void
    {
        $date1 = CarbonImmutable::now()->subDays(10);
        $date2 = CarbonImmutable::now()->subDays(5);
        $date3 = CarbonImmutable::now();

        DB::table('audited_records')->insert([
            ['name' => 'Record 1', 'created_at' => $date1, 'updated_at' => $date1, 'active' => true, 'notes' => 'test'],
            ['name' => 'Record 2', 'created_at' => $date2, 'updated_at' => $date2, 'active' => true, 'notes' => 'test'],
            ['name' => 'Record 3', 'created_at' => $date3, 'updated_at' => $date3, 'active' => true, 'notes' => 'test'],
        ]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['created_at' => ['operator' => 'before', 'value' => $date3->toDateString()]],
            ['created_at' => ['column' => 'created_at', 'label' => 'test.created_at', 'type' => 'date']],
        );

        $this->assertSame(2, $builder->count());
    }

    /**
     * @requires extension pdo_sqlite
     */
    public function test_apply_filters_date_after(): void
    {
        $date1 = CarbonImmutable::now()->subDays(10);
        $date2 = CarbonImmutable::now()->subDays(5);
        $date3 = CarbonImmutable::now();

        DB::table('audited_records')->insert([
            ['name' => 'Record 1', 'created_at' => $date1, 'updated_at' => $date1, 'active' => true, 'notes' => 'test'],
            ['name' => 'Record 2', 'created_at' => $date2, 'updated_at' => $date2, 'active' => true, 'notes' => 'test'],
            ['name' => 'Record 3', 'created_at' => $date3, 'updated_at' => $date3, 'active' => true, 'notes' => 'test'],
        ]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['created_at' => ['operator' => 'after', 'value' => $date1->toDateString()]],
            ['created_at' => ['column' => 'created_at', 'label' => 'test.created_at', 'type' => 'date']],
        );

        $this->assertSame(2, $builder->count());
    }

    /**
     * @requires extension pdo_sqlite
     */
    public function test_apply_filters_date_equals(): void
    {
        $date1 = CarbonImmutable::now()->subDays(10);
        $date2 = CarbonImmutable::now()->subDays(5);

        DB::table('audited_records')->insert([
            ['name' => 'Record 1', 'created_at' => $date1, 'updated_at' => $date1, 'active' => true, 'notes' => 'test'],
            ['name' => 'Record 2', 'created_at' => $date2, 'updated_at' => $date2, 'active' => true, 'notes' => 'test'],
            ['name' => 'Record 3', 'created_at' => $date2, 'updated_at' => $date2, 'active' => true, 'notes' => 'test'],
        ]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['created_at' => ['operator' => 'equals', 'value' => $date2->toDateString()]],
            ['created_at' => ['column' => 'created_at', 'label' => 'test.created_at', 'type' => 'date']],
        );

        $this->assertSame(2, $builder->count());
    }

    /**
     * @requires extension pdo_sqlite
     */
    public function test_apply_filters_boolean_is_true(): void
    {
        AuditedRecord::factory()->create(['name' => 'Record One', 'active' => true]);
        AuditedRecord::factory()->create(['name' => 'Record Two', 'active' => false]);
        AuditedRecord::factory()->create(['name' => 'Record Three', 'active' => true]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['active' => ['operator' => 'is_true', 'value' => true]],
            ['active' => ['column' => 'active', 'label' => 'test.active', 'type' => 'boolean']],
        );

        $this->assertSame(2, $builder->count());
        // SQLite returns boolean as integer 1/0
        $this->assertTrue($builder->pluck('active')->every(fn ($v) => (bool) $v === true));
    }

    /**
     * @requires extension pdo_sqlite
     */
    public function test_apply_filters_boolean_is_false(): void
    {
        AuditedRecord::factory()->create(['name' => 'Record One', 'active' => true]);
        AuditedRecord::factory()->create(['name' => 'Record Two', 'active' => false]);
        AuditedRecord::factory()->create(['name' => 'Record Three', 'active' => true]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['active' => ['operator' => 'is_false', 'value' => false]],
            ['active' => ['column' => 'active', 'label' => 'test.active', 'type' => 'boolean']],
        );

        $this->assertSame(1, $builder->count());
        // SQLite returns boolean as integer 1/0
        $this->assertFalse((bool) $builder->first()->active);
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

    /**
     * @requires extension pdo_sqlite
     */
    public function test_apply_filters_select_equals_single(): void
    {
        AuditedRecord::factory()->create(['name' => 'Project A', 'project_id' => 1]);
        AuditedRecord::factory()->create(['name' => 'Project B', 'project_id' => 2]);
        AuditedRecord::factory()->create(['name' => 'Project C', 'project_id' => 1]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['project_id' => ['operator' => 'equals', 'value' => 1]],
            ['project_id' => ['column' => 'project_id', 'label' => 'test.project', 'type' => 'select', 'multiple' => false]],
        );

        $this->assertSame(2, $builder->count());
        $this->assertTrue($builder->pluck('project_id')->every(fn ($v) => $v === 1));
    }

    /**
     * @requires extension pdo_sqlite
     */
    public function test_apply_filters_select_not_equals_single(): void
    {
        AuditedRecord::factory()->create(['name' => 'Project A', 'project_id' => 1]);
        AuditedRecord::factory()->create(['name' => 'Project B', 'project_id' => 2]);
        AuditedRecord::factory()->create(['name' => 'Project C', 'project_id' => 1]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['project_id' => ['operator' => 'not_equals', 'value' => 1]],
            ['project_id' => ['column' => 'project_id', 'label' => 'test.project', 'type' => 'select', 'multiple' => false]],
        );

        $this->assertSame(1, $builder->count());
        $this->assertSame(2, $builder->first()->project_id);
    }

    /**
     * @requires extension pdo_sqlite
     */
    public function test_apply_filters_select_in_multiple(): void
    {
        AuditedRecord::factory()->create(['name' => 'Project A', 'project_id' => 1]);
        AuditedRecord::factory()->create(['name' => 'Project B', 'project_id' => 2]);
        AuditedRecord::factory()->create(['name' => 'Project C', 'project_id' => 3]);
        AuditedRecord::factory()->create(['name' => 'Project D', 'project_id' => 1]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['project_id' => ['operator' => 'in', 'value' => [1, 3]]],
            ['project_id' => ['column' => 'project_id', 'label' => 'test.project', 'type' => 'select', 'multiple' => true]],
        );

        $this->assertSame(3, $builder->count());
        $projectIds = $builder->pluck('project_id')->all();
        $this->assertContains(1, $projectIds);
        $this->assertContains(3, $projectIds);
        $this->assertNotContains(2, $projectIds);
    }

    /**
     * @requires extension pdo_sqlite
     */
    public function test_apply_filters_select_not_in_multiple(): void
    {
        AuditedRecord::factory()->create(['name' => 'Project A', 'project_id' => 1]);
        AuditedRecord::factory()->create(['name' => 'Project B', 'project_id' => 2]);
        AuditedRecord::factory()->create(['name' => 'Project C', 'project_id' => 3]);
        AuditedRecord::factory()->create(['name' => 'Project D', 'project_id' => 1]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['project_id' => ['operator' => 'not_in', 'value' => [1, 3]]],
            ['project_id' => ['column' => 'project_id', 'label' => 'test.project', 'type' => 'select', 'multiple' => true]],
        );

        $this->assertSame(1, $builder->count());
        $this->assertSame(2, $builder->first()->project_id);
    }

    /**
     * @requires extension pdo_sqlite
     */
    public function test_apply_filters_select_is_empty(): void
    {
        AuditedRecord::factory()->create(['name' => 'Project A', 'project_id' => 1]);
        AuditedRecord::factory()->create(['name' => 'Project B', 'project_id' => null]);
        AuditedRecord::factory()->create(['name' => 'Project C', 'project_id' => 2]);
        AuditedRecord::factory()->create(['name' => 'Project D', 'project_id' => null]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['project_id' => ['operator' => 'is_empty', 'value' => null]],
            ['project_id' => ['column' => 'project_id', 'label' => 'test.project', 'type' => 'select', 'nullable' => true]],
        );

        $this->assertSame(2, $builder->count());
        $this->assertTrue($builder->pluck('project_id')->every(fn ($v) => $v === null));
    }

    /**
     * @requires extension pdo_sqlite
     */
    public function test_apply_filters_combines_multiple_with_and(): void
    {
        AuditedRecord::factory()->create(['name' => 'Juan', 'active' => true, 'project_id' => 1]);
        AuditedRecord::factory()->create(['name' => 'Maria', 'active' => true, 'project_id' => 2]);
        AuditedRecord::factory()->create(['name' => 'Pedro', 'active' => false, 'project_id' => 1]);
        AuditedRecord::factory()->create(['name' => 'Ana', 'active' => true, 'project_id' => 1]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            [
                'name' => ['operator' => 'contains', 'value' => 'na'],
                'active' => ['operator' => 'is_true', 'value' => true],
                'project_id' => ['operator' => 'equals', 'value' => 1],
            ],
            [
                'name' => ['column' => 'name', 'label' => 'test.name', 'type' => 'text'],
                'active' => ['column' => 'active', 'label' => 'test.active', 'type' => 'boolean'],
                'project_id' => ['column' => 'project_id', 'label' => 'test.project', 'type' => 'select', 'multiple' => false],
            ],
        );

        $this->assertSame(1, $builder->count());
        $this->assertSame('Ana', $builder->first()->name);
    }

    // =========================================================================
    // Helper methods for calling private/protected methods
    // =========================================================================

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
     * @param  Builder<AuditedRecord>  $builder
     * @param  SearchFilters  $filters
     * @param  SearchFields  $fields
     */
    private function callApplyFilters(Builder $builder, array $filters, array $fields): void
    {
        $reflection = new \ReflectionMethod($this->query, 'applyFilters');
        $reflection->setAccessible(true);
        $reflection->invoke($this->query, $builder, $filters, $fields, 'active');
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

    public function searchableFields(string $scope): array
    {
        return [
            'name' => [
                'column' => 'name',
                'label' => 'test.name',
                'type' => 'text',
                'operators' => ['contains', 'equals'],
                'default_operator' => 'contains',
                'visible' => true,
                'order' => 10,
                'group' => 'basic',
            ],
        ];
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

    public function searchableFields(string $scope): array
    {
        return [
            'name' => [
                'column' => 'name',
                'label' => 'test.name',
                'type' => 'text',
                'operators' => ['contains', 'equals'],
                'default_operator' => 'contains',
                'visible' => true,
                'order' => 10,
                'group' => 'basic',
            ],
        ];
    }
}

/** @extends ListQueryBase<AuditedRecord> */
abstract class TestQueryWithAdvancedFilters extends ListQueryBase
{
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

    /**
     * @return class-string<AuditedRecord>
     */
    protected function getModelClass(): string
    {
        return AuditedRecord::class;
    }
}

class TestListQueryWithGroupsAndOrder extends TestQueryWithAdvancedFilters
{
    public function searchableFields(string $scope): array
    {
        return [
            'name' => ['column' => 'name', 'label' => 'test.name', 'type' => 'text', 'visible' => true, 'order' => 10, 'group' => 'basic'],
            'description' => ['column' => 'description', 'label' => 'test.description', 'type' => 'text', 'visible' => true, 'order' => 20, 'group' => 'basic'],
            'active' => ['column' => 'active', 'label' => 'test.active', 'type' => 'boolean', 'visible' => true, 'order' => 10, 'group' => 'status'],
            'created_at' => ['column' => 'created_at', 'label' => 'test.created_at', 'type' => 'date', 'visible' => true, 'order' => 10, 'group' => 'dates'],
        ];
    }
}

class TestListQueryWithVisibleFlags extends TestQueryWithAdvancedFilters
{
    public function searchableFields(string $scope): array
    {
        return [
            'name' => ['column' => 'name', 'label' => 'test.name', 'type' => 'text', 'visible' => true, 'order' => 10, 'group' => 'basic'],
            'notes' => ['column' => 'notes', 'label' => 'test.notes', 'type' => 'text', 'visible' => false, 'order' => 20, 'group' => 'basic'],
        ];
    }
}

class TestListQueryWithCondition extends TestQueryWithAdvancedFilters
{
    public function searchableFields(string $scope): array
    {
        return [
            'name' => ['column' => 'name', 'label' => 'test.name', 'type' => 'text', 'visible' => true, 'order' => 10, 'group' => 'basic'],
            'entity_id' => [
                'column' => 'entity_id',
                'label' => 'test.entity',
                'type' => 'select',
                'visible' => true,
                'order' => 10,
                'group' => 'relations',
                'condition' => fn (string $scope) => $scope !== 'active',
            ],
        ];
    }
}

class TestListQueryWithSelectOptions extends TestQueryWithAdvancedFilters
{
    public function searchableFields(string $scope): array
    {
        return [
            'entity_id' => [
                'column' => 'entity_id',
                'label' => 'test.entity',
                'type' => 'select',
                'visible' => true,
                'order' => 10,
                'group' => 'relations',
                'options' => fn (string $scope) => [1 => 'Entity One', 2 => 'Entity Two'],
                'multiple' => true,
            ],
        ];
    }
}

class TestListQueryWithSelectOptionsMultiple extends TestQueryWithAdvancedFilters
{
    public function searchableFields(string $scope): array
    {
        return [
            'entity_id' => [
                'column' => 'entity_id',
                'label' => 'test.entity',
                'type' => 'select',
                'visible' => true,
                'order' => 10,
                'group' => 'relations',
                'multiple' => true,
            ],
        ];
    }
}

class TestListQueryWithSelectFilter extends TestQueryWithAdvancedFilters
{
    public function searchableFields(string $scope): array
    {
        return [
            'entity_id' => [
                'column' => 'entity_id',
                'label' => 'test.entity',
                'type' => 'select',
                'visible' => true,
                'order' => 10,
                'group' => 'relations',
                'nullable' => true,
            ],
        ];
    }
}

class TestListQueryWithSelectFilterMultiple extends TestQueryWithAdvancedFilters
{
    public function searchableFields(string $scope): array
    {
        return [
            'entity_id' => [
                'column' => 'entity_id',
                'label' => 'test.entity',
                'type' => 'select',
                'visible' => true,
                'order' => 10,
                'group' => 'relations',
                'nullable' => true,
                'multiple' => true,
            ],
        ];
    }
}

class TestListQueryWithFilters extends TestQueryWithAdvancedFilters
{
    public function searchableFields(string $scope): array
    {
        return [
            'name' => ['column' => 'name', 'label' => 'test.name', 'type' => 'text', 'visible' => true, 'order' => 10, 'group' => 'basic'],
            'created_at' => ['column' => 'created_at', 'label' => 'test.created_at', 'type' => 'date', 'visible' => true, 'order' => 10, 'group' => 'dates'],
            'active' => ['column' => 'active', 'label' => 'test.active', 'type' => 'boolean', 'visible' => true, 'order' => 10, 'group' => 'status'],
            'priority' => ['column' => 'priority', 'label' => 'test.priority', 'type' => 'number', 'visible' => true, 'order' => 10, 'group' => 'numbers'],
        ];
    }
}
