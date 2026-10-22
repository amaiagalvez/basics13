<?php

namespace Basics13\Tests\Feature\Queries;

use Carbon\CarbonImmutable;
use Basics13\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Basics13\Queries\ListQueryBase;
use Basics13\Tests\Fixtures\AuditActor;
use Illuminate\Database\Eloquent\Builder;
use Basics13\Tests\Fixtures\AuditedRecord;
use Basics13\Tests\Fixtures\TestListQuery;
use PHPUnit\Framework\Attributes\DataProvider;
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

    // =========================================================================
    // Advanced Search: applyFilters() operator mapping
    // =========================================================================

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
            ['project_id' => ['column' => 'project_id', 'label' => 'test.project', 'type' => 'select', 'operators' => ['in'], 'multiple' => true]],
        );

        $this->assertSame(3, $builder->count());
        $projectIds = $builder->pluck('project_id')->all();
        $this->assertContains(1, $projectIds);
        $this->assertContains(3, $projectIds);
        $this->assertNotContains(2, $projectIds);
    }

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
            ['project_id' => ['column' => 'project_id', 'label' => 'test.project', 'type' => 'select', 'operators' => ['not_in'], 'multiple' => true]],
        );

        $this->assertSame(1, $builder->count());
        $this->assertSame(2, $builder->first()->project_id);
    }

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
    // Additional filter operator coverage
    // =========================================================================

    public function test_apply_filters_number_greater_than(): void
    {
        DB::table('audited_records')->insert([
            ['name' => 'Record 1', 'priority' => 5, 'active' => true, 'updated_at' => now(), 'notes' => 'test'],
            ['name' => 'Record 2', 'priority' => 15, 'active' => true, 'updated_at' => now(), 'notes' => 'test'],
            ['name' => 'Record 3', 'priority' => 25, 'active' => true, 'updated_at' => now(), 'notes' => 'test'],
        ]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['priority' => ['operator' => 'greater_than', 'value' => 10]],
            ['priority' => ['column' => 'priority', 'label' => 'test.priority', 'type' => 'number']],
        );

        $this->assertSame(2, $builder->count());
        $priorities = $builder->pluck('priority')->all();
        $this->assertTrue(in_array(15, $priorities));
        $this->assertTrue(in_array(25, $priorities));
    }

    public function test_apply_filters_number_less_than(): void
    {
        DB::table('audited_records')->insert([
            ['name' => 'Record 1', 'priority' => 5, 'active' => true, 'updated_at' => now(), 'notes' => 'test'],
            ['name' => 'Record 2', 'priority' => 15, 'active' => true, 'updated_at' => now(), 'notes' => 'test'],
            ['name' => 'Record 3', 'priority' => 25, 'active' => true, 'updated_at' => now(), 'notes' => 'test'],
        ]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['priority' => ['operator' => 'less_than', 'value' => 20]],
            ['priority' => ['column' => 'priority', 'label' => 'test.priority', 'type' => 'number']],
        );

        $this->assertSame(2, $builder->count());
        $priorities = $builder->pluck('priority')->all();
        $this->assertTrue(in_array(5, $priorities));
        $this->assertTrue(in_array(15, $priorities));
    }

    public function test_apply_filters_number_greater_than_or_equal(): void
    {
        DB::table('audited_records')->insert([
            ['name' => 'Record 1', 'priority' => 10, 'active' => true, 'updated_at' => now(), 'notes' => 'test'],
            ['name' => 'Record 2', 'priority' => 15, 'active' => true, 'updated_at' => now(), 'notes' => 'test'],
            ['name' => 'Record 3', 'priority' => 20, 'active' => true, 'updated_at' => now(), 'notes' => 'test'],
        ]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['priority' => ['operator' => 'greater_than_or_equal', 'value' => 15]],
            ['priority' => ['column' => 'priority', 'label' => 'test.priority', 'type' => 'number']],
        );

        $this->assertSame(2, $builder->count());
        $priorities = $builder->pluck('priority')->all();
        $this->assertTrue(in_array(15, $priorities));
        $this->assertTrue(in_array(20, $priorities));
    }

    public function test_apply_filters_number_less_than_or_equal(): void
    {
        DB::table('audited_records')->insert([
            ['name' => 'Record 1', 'priority' => 10, 'active' => true, 'updated_at' => now(), 'notes' => 'test'],
            ['name' => 'Record 2', 'priority' => 15, 'active' => true, 'updated_at' => now(), 'notes' => 'test'],
            ['name' => 'Record 3', 'priority' => 20, 'active' => true, 'updated_at' => now(), 'notes' => 'test'],
        ]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['priority' => ['operator' => 'less_than_or_equal', 'value' => 15]],
            ['priority' => ['column' => 'priority', 'label' => 'test.priority', 'type' => 'number']],
        );

        $this->assertSame(2, $builder->count());
        $priorities = $builder->pluck('priority')->all();
        $this->assertTrue(in_array(10, $priorities));
        $this->assertTrue(in_array(15, $priorities));
    }

    public function test_apply_filters_number_not_equals(): void
    {
        DB::table('audited_records')->insert([
            ['name' => 'Record 1', 'priority' => 10, 'active' => true, 'updated_at' => now(), 'notes' => 'test'],
            ['name' => 'Record 2', 'priority' => 15, 'active' => true, 'updated_at' => now(), 'notes' => 'test'],
            ['name' => 'Record 3', 'priority' => 20, 'active' => true, 'updated_at' => now(), 'notes' => 'test'],
        ]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['priority' => ['operator' => 'not_equals', 'value' => 15]],
            ['priority' => ['column' => 'priority', 'label' => 'test.priority', 'type' => 'number']],
        );

        $this->assertSame(2, $builder->count());
        $priorities = $builder->pluck('priority')->all();
        $this->assertTrue(in_array(10, $priorities));
        $this->assertTrue(in_array(20, $priorities));
        $this->assertFalse(in_array(15, $priorities));
    }

    public function test_apply_filters_date_is_empty(): void
    {
        DB::table('audited_records')->insert([
            ['name' => 'Record 1', 'published_at' => '2024-01-15', 'active' => true, 'updated_at' => now(), 'notes' => 'test'],
            ['name' => 'Record 2', 'published_at' => null, 'active' => true, 'updated_at' => now(), 'notes' => 'test'],
            ['name' => 'Record 3', 'published_at' => '2024-03-15', 'active' => true, 'updated_at' => now(), 'notes' => 'test'],
        ]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['published_at' => ['operator' => 'is_empty', 'value' => null]],
            ['published_at' => ['column' => 'published_at', 'label' => 'test.published_at', 'type' => 'date', 'nullable' => true]],
        );

        $this->assertSame(1, $builder->count());
        $this->assertNull($builder->first()->published_at);
    }

    public function test_apply_filters_boolean_default_operator(): void
    {
        AuditedRecord::factory()->create(['name' => 'Record One', 'active' => true]);
        AuditedRecord::factory()->create(['name' => 'Record Two', 'active' => false]);
        AuditedRecord::factory()->create(['name' => 'Record Three', 'active' => true]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['active' => ['operator' => 'is_true', 'value' => 'true']],
            ['active' => ['column' => 'active', 'label' => 'test.active', 'type' => 'boolean']],
        );

        $this->assertSame(2, $builder->count());
    }

    public function test_apply_filters_text_equals_exact_match(): void
    {
        AuditedRecord::factory()->create(['name' => 'Juan Perez', 'active' => true]);
        AuditedRecord::factory()->create(['name' => 'Maria Garcia', 'active' => true]);
        AuditedRecord::factory()->create(['name' => 'Pedro Lopez', 'active' => true]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['name' => ['operator' => 'equals', 'value' => 'Juan Perez']],
            ['name' => ['column' => 'name', 'label' => 'test.name', 'type' => 'text']],
        );

        $this->assertSame(1, $builder->count());
        $this->assertSame('Juan Perez', $builder->first()->name);
    }

    public function test_apply_filters_select_multiple_equals_single_value_as_array(): void
    {
        AuditedRecord::factory()->create(['name' => 'Project A', 'project_id' => 1]);
        AuditedRecord::factory()->create(['name' => 'Project B', 'project_id' => 2]);
        AuditedRecord::factory()->create(['name' => 'Project C', 'project_id' => 1]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['project_id' => ['operator' => 'equals', 'value' => [1]]],
            ['project_id' => ['column' => 'project_id', 'label' => 'test.project', 'type' => 'select', 'multiple' => true]],
        );

        $this->assertSame(2, $builder->count());
        $this->assertTrue($builder->pluck('project_id')->every(fn ($v) => $v === 1));
    }

    public function test_apply_filters_select_multiple_not_equals_single_value_as_array(): void
    {
        AuditedRecord::factory()->create(['name' => 'Project A', 'project_id' => 1]);
        AuditedRecord::factory()->create(['name' => 'Project B', 'project_id' => 2]);
        AuditedRecord::factory()->create(['name' => 'Project C', 'project_id' => 1]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['project_id' => ['operator' => 'not_equals', 'value' => [1]]],
            ['project_id' => ['column' => 'project_id', 'label' => 'test.project', 'type' => 'select', 'multiple' => true]],
        );

        $this->assertSame(1, $builder->count());
        $this->assertSame(2, $builder->first()->project_id);
    }

    public function test_apply_filters_select_is_empty_non_nullable_skips(): void
    {
        AuditedRecord::factory()->create(['name' => 'Project A', 'project_id' => 1]);
        AuditedRecord::factory()->create(['name' => 'Project B', 'project_id' => 2]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['project_id' => ['operator' => 'is_empty', 'value' => null]],
            ['project_id' => ['column' => 'project_id', 'label' => 'test.project', 'type' => 'select', 'nullable' => false]],
        );

        // Should not filter since field is not nullable
        $this->assertSame(2, $builder->count());
    }

    public function test_apply_filters_select_multiple_in_with_empty_array(): void
    {
        AuditedRecord::factory()->create(['name' => 'Project A', 'project_id' => 1]);
        AuditedRecord::factory()->create(['name' => 'Project B', 'project_id' => 2]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['project_id' => ['operator' => 'in', 'value' => []]],
            ['project_id' => ['column' => 'project_id', 'label' => 'test.project', 'type' => 'select', 'multiple' => true]],
        );

        // Empty array should result in no filtering (or empty result depending on implementation)
        $this->assertSame(2, $builder->count());
    }

    public function test_apply_filters_skips_unknown_field(): void
    {
        AuditedRecord::factory()->create(['name' => 'Record 1', 'active' => true]);
        AuditedRecord::factory()->create(['name' => 'Record 2', 'active' => true]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['unknown_field' => ['operator' => 'equals', 'value' => 'test']],
            ['name' => ['column' => 'name', 'label' => 'test.name', 'type' => 'text']],
        );

        // Should not filter since field is unknown
        $this->assertSame(2, $builder->count());
    }

    public function test_apply_filters_number_between_invalid_value_skips(): void
    {
        DB::table('audited_records')->insert([
            ['name' => 'Record 1', 'priority' => 10, 'active' => true, 'updated_at' => now(), 'notes' => 'test'],
            ['name' => 'Record 2', 'priority' => 20, 'active' => true, 'updated_at' => now(), 'notes' => 'test'],
        ]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['priority' => ['operator' => 'between', 'value' => 'not-an-array']],
            ['priority' => ['column' => 'priority', 'label' => 'test.priority', 'type' => 'number']],
        );

        // Invalid value should skip filter
        $this->assertSame(2, $builder->count());
    }

    public function test_apply_filters_number_between_non_numeric_skips(): void
    {
        DB::table('audited_records')->insert([
            ['name' => 'Record 1', 'priority' => 10, 'active' => true, 'updated_at' => now(), 'notes' => 'test'],
            ['name' => 'Record 2', 'priority' => 20, 'active' => true, 'updated_at' => now(), 'notes' => 'test'],
        ]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['priority' => ['operator' => 'between', 'value' => ['a', 'b']]],
            ['priority' => ['column' => 'priority', 'label' => 'test.priority', 'type' => 'number']],
        );

        // Non-numeric values should skip filter
        $this->assertSame(2, $builder->count());
    }

    public function test_apply_filters_text_empty_value_skips(): void
    {
        AuditedRecord::factory()->create(['name' => 'Record 1', 'active' => true]);
        AuditedRecord::factory()->create(['name' => 'Record 2', 'active' => true]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['name' => ['operator' => 'contains', 'value' => '']],
            ['name' => ['column' => 'name', 'label' => 'test.name', 'type' => 'text']],
        );

        // Empty value should skip filter
        $this->assertSame(2, $builder->count());
    }

    /**
     * `%`, `_` and the escape character itself match literally, as they do in the simple search.
     *
     * @return array<string, array{string, string, list<string>}>
     */
    public static function likeWildcardFilters(): array
    {
        return [
            'contains percent' => ['contains', '50%', ['Save 50% now', 'Save 50%']],
            'contains underscore' => ['contains', 'a_b', ['x a_b y', 'a_b']],
            'contains escape character' => ['contains', 'Hi!', ['Hi! there']],
            'starts with underscore' => ['starts_with', 'a_', ['a_b']],
            'ends with percent' => ['ends_with', '0%', ['Save 50%']],
        ];
    }

    /**
     * @param  list<string>  $expected
     */
    #[DataProvider('likeWildcardFilters')]
    public function test_apply_filters_text_matches_like_wildcards_literally(string $operator, string $value, array $expected): void
    {
        foreach (['Save 50% now', 'Save 500 now', 'x a_b y', 'x axb y', 'Hi! there', 'Hi there', 'a_b', 'axb', 'Save 50%', 'Save 500'] as $name) {
            AuditedRecord::create(['name' => $name, 'notes' => 'test']);
        }

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['name' => ['operator' => $operator, 'value' => $value]],
            ['name' => ['column' => 'name', 'label' => 'test.name', 'type' => 'text']],
        );

        $this->assertEqualsCanonicalizing($expected, $builder->pluck('name')->all());
    }

    public function test_apply_filters_skips_an_operator_the_field_does_not_declare(): void
    {
        AuditedRecord::create(['name' => 'Alpha', 'notes' => 'test']);
        AuditedRecord::create(['name' => 'Beta', 'notes' => 'test']);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            [
                'name' => ['operator' => 'starts_with', 'value' => 'Al'],
                'active' => ['operator' => 'anything', 'value' => '0'],
            ],
            [
                'name' => ['column' => 'name', 'label' => 'test.name', 'type' => 'text', 'operators' => ['contains', 'equals']],
                'active' => ['column' => 'active', 'label' => 'test.active', 'type' => 'boolean'],
            ],
        );

        $this->assertSame(2, $builder->count());
    }

    public function test_apply_filters_skips_a_value_shaped_for_another_operator(): void
    {
        AuditedRecord::create(['name' => 'Alpha', 'notes' => 'test']);
        AuditedRecord::create(['name' => 'Beta', 'notes' => 'test']);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            [
                'name' => ['operator' => 'contains', 'value' => ['Al']],
                'created_at' => ['operator' => 'before', 'value' => ['2000-01-01', '2000-02-01']],
            ],
            [
                'name' => ['column' => 'name', 'label' => 'test.name', 'type' => 'text'],
                'created_at' => ['column' => 'created_at', 'label' => 'test.created_at', 'type' => 'date'],
            ],
        );

        $this->assertSame(2, $builder->count());
    }

    public function test_apply_filters_number_empty_value_skips(): void
    {
        DB::table('audited_records')->insert([
            ['name' => 'Record 1', 'priority' => 10, 'active' => true, 'updated_at' => now(), 'notes' => 'test'],
            ['name' => 'Record 2', 'priority' => 20, 'active' => true, 'updated_at' => now(), 'notes' => 'test'],
        ]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['priority' => ['operator' => 'equals', 'value' => '']],
            ['priority' => ['column' => 'priority', 'label' => 'test.priority', 'type' => 'number']],
        );

        // Empty value should skip filter
        $this->assertSame(2, $builder->count());
    }

    // =========================================================================
    // Advanced Search: branches reached only through declared operators
    // =========================================================================

    public function test_apply_filters_text_ignores_an_operator_without_a_mapping(): void
    {
        $this->createRecordsWithPriorities([10, 20]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['name' => ['operator' => 'sounds_like', 'value' => 'Record 1']],
            ['name' => ['column' => 'name', 'label' => 'test.name', 'type' => 'text', 'operators' => ['sounds_like']]],
        );

        $this->assertSame(2, $builder->count());
    }

    public function test_apply_filters_date_ignores_an_operator_without_a_mapping(): void
    {
        $this->createRecordsWithPriorities([10, 20]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['published_at' => ['operator' => 'in_week', 'value' => '2026-01-01']],
            ['published_at' => ['column' => 'published_at', 'label' => 'test.published_at', 'type' => 'date', 'operators' => ['in_week']]],
        );

        $this->assertSame(2, $builder->count());
    }

    public function test_apply_filters_boolean_any_operator_leaves_the_query_untouched(): void
    {
        AuditedRecord::factory()->create(['name' => 'Active']);
        AuditedRecord::factory()->archived()->create(['name' => 'Archived']);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['active' => ['operator' => '', 'value' => null]],
            ['active' => ['column' => 'active', 'label' => 'test.active', 'type' => 'boolean', 'operators' => ['', 'is_true']]],
        );

        $this->assertSame(2, $builder->count());
    }

    public function test_apply_filters_boolean_equals_compares_with_the_cast_value(): void
    {
        AuditedRecord::factory()->create(['name' => 'Active']);
        AuditedRecord::factory()->archived()->create(['name' => 'Archived']);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['active' => ['operator' => 'equals', 'value' => 'false']],
            ['active' => ['column' => 'active', 'label' => 'test.active', 'type' => 'boolean', 'operators' => ['equals']]],
        );

        $this->assertSame(['Archived'], $builder->pluck('name')->all());
    }

    public function test_apply_filters_number_equals(): void
    {
        $this->createRecordsWithPriorities([10, 20]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['priority' => ['operator' => 'equals', 'value' => '20']],
            ['priority' => ['column' => 'priority', 'label' => 'test.priority', 'type' => 'number']],
        );

        $this->assertSame([20], $builder->pluck('priority')->all());
    }

    public function test_apply_filters_number_skips_a_non_numeric_value(): void
    {
        $this->createRecordsWithPriorities([10, 20]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['priority' => ['operator' => 'equals', 'value' => 'ten']],
            ['priority' => ['column' => 'priority', 'label' => 'test.priority', 'type' => 'number']],
        );

        $this->assertSame(2, $builder->count());
    }

    public function test_apply_filters_number_skips_an_empty_value_even_for_is_empty(): void
    {
        $this->createRecordsWithPriorities([10, 20]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['priority' => ['operator' => 'is_empty', 'value' => null]],
            ['priority' => ['column' => 'priority', 'label' => 'test.priority', 'type' => 'number', 'operators' => ['is_empty']]],
        );

        $this->assertSame(2, $builder->count());
    }

    public function test_apply_filters_number_ignores_an_operator_without_a_mapping(): void
    {
        $this->createRecordsWithPriorities([10, 20]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['priority' => ['operator' => 'multiple_of', 'value' => 10]],
            ['priority' => ['column' => 'priority', 'label' => 'test.priority', 'type' => 'number', 'operators' => ['multiple_of']]],
        );

        $this->assertSame(2, $builder->count());
    }

    public function test_apply_filters_select_is_empty_on_a_non_nullable_field_skips_the_empty_value(): void
    {
        AuditedRecord::factory()->create(['name' => 'Project A', 'project_id' => 1]);
        AuditedRecord::factory()->create(['name' => 'Project B', 'project_id' => null]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['project_id' => ['operator' => 'is_empty', 'value' => null]],
            ['project_id' => ['column' => 'project_id', 'label' => 'test.project', 'type' => 'select', 'operators' => ['is_empty']]],
        );

        $this->assertSame(2, $builder->count());
    }

    public function test_apply_filters_select_skips_a_list_of_blank_values(): void
    {
        AuditedRecord::factory()->create(['name' => 'Project A', 'project_id' => 1]);
        AuditedRecord::factory()->create(['name' => 'Project B', 'project_id' => 2]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['project_id' => ['operator' => 'equals', 'value' => [null, '']]],
            ['project_id' => ['column' => 'project_id', 'label' => 'test.project', 'type' => 'select']],
        );

        $this->assertSame(2, $builder->count());
    }

    public function test_apply_filters_multi_select_treats_an_unmapped_operator_as_in(): void
    {
        AuditedRecord::factory()->create(['name' => 'Project A', 'project_id' => 1]);
        AuditedRecord::factory()->create(['name' => 'Project B', 'project_id' => 2]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['project_id' => ['operator' => 'any_of', 'value' => [2]]],
            ['project_id' => ['column' => 'project_id', 'label' => 'test.project', 'type' => 'select', 'operators' => ['any_of']]],
        );

        $this->assertSame(['Project B'], $builder->pluck('name')->all());
    }

    public function test_apply_filters_single_select_treats_an_unmapped_operator_as_equals(): void
    {
        AuditedRecord::factory()->create(['name' => 'Project A', 'project_id' => 1]);
        AuditedRecord::factory()->create(['name' => 'Project B', 'project_id' => 2]);

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['project_id' => ['operator' => 'is', 'value' => 1]],
            ['project_id' => ['column' => 'project_id', 'label' => 'test.project', 'type' => 'select', 'multiple' => false, 'operators' => ['is']]],
        );

        $this->assertSame(['Project A'], $builder->pluck('name')->all());
    }

    // =========================================================================
    // Advanced Search: relation filters
    // =========================================================================

    /**
     * @return array<string, array{string, mixed, list<string>}>
     */
    public static function relationFilters(): array
    {
        return [
            'equals keeps the records holding a selected value' => ['equals', ['ana'], ['By Ana']],
            'not_equals keeps the records holding none of them' => ['not_equals', ['ana'], ['By Ben', 'Nobody']],
            'is_empty keeps the records holding none at all' => ['is_empty', null, ['Nobody']],
            'blank values leave the query untouched' => ['equals', [null, ''], ['By Ana', 'By Ben', 'Nobody']],
        ];
    }

    /**
     * @param  list<string>  $expected
     */
    #[DataProvider('relationFilters')]
    public function test_apply_filters_on_a_relation(string $operator, mixed $value, array $expected): void
    {
        $actors = ['ana' => AuditActor::factory()->create(), 'ben' => AuditActor::factory()->create()];
        $this->createRecordCreatedBy('By Ana', $actors['ana']->getKey());
        $this->createRecordCreatedBy('By Ben', $actors['ben']->getKey());
        $this->createRecordCreatedBy('Nobody', null);

        $value = is_array($value)
            ? array_map(fn (?string $key) => $key === null || $key === '' ? $key : $actors[$key]->getKey(), $value)
            : $value;

        $builder = AuditedRecord::query();
        $this->callApplyFilters(
            $builder,
            ['creator' => ['operator' => $operator, 'value' => $value]],
            ['creator' => ['column' => 'id', 'label' => 'test.creator', 'type' => 'select', 'nullable' => true, 'relation' => 'creator']],
        );

        $this->assertEqualsCanonicalizing($expected, $builder->pluck('name')->all());
    }

    /**
     * @param  list<int>  $priorities
     */
    private function createRecordsWithPriorities(array $priorities): void
    {
        foreach ($priorities as $index => $priority) {
            AuditedRecord::factory()->create(['name' => 'Record '.($index + 1), 'priority' => $priority]);
        }
    }

    private function createRecordCreatedBy(string $name, mixed $actorId): void
    {
        $record = AuditedRecord::factory()->create(['name' => $name]);
        DB::table('audited_records')->where('id', $record->getKey())->update(['created_by' => $actorId]);
    }

    // =========================================================================
    // stateCounts with default null parameters
    // =========================================================================

    public function test_state_counts_calculates_when_not_provided(): void
    {
        AuditedRecord::factory()->create(['name' => 'Active 1', 'active' => true]);
        AuditedRecord::factory()->create(['name' => 'Active 2', 'active' => true]);
        $archived = AuditedRecord::factory()->create(['name' => 'Archived 1', 'active' => false]);
        $archived->delete();
        AuditedRecord::factory()->create(['name' => 'Archived 2', 'active' => false]);
        $trashed = AuditedRecord::factory()->create(['name' => 'Trashed 1', 'active' => true]);
        $trashed->delete();

        $query = new TestListQuery;
        $result = $this->callStateCounts($query);

        // Active: 2 (Active 1, Active 2)
        // Archived: 1 (Archived 2 - not deleted, active=false)
        // Trashed: 2 (Archived 1 - deleted, active=false; Trashed 1 - deleted, active=true)
        $this->assertSame(2, $result['active']);
        $this->assertSame(1, $result['archived']);
        $this->assertSame(2, $result['trashed']);
    }

    // =========================================================================
    // findTrashedByName
    // =========================================================================

    public function test_find_trashed_by_name_returns_most_recently_deleted(): void
    {
        $record1 = AuditedRecord::factory()->create(['name' => 'Test Record', 'active' => true]);
        $record1->delete();

        $record2 = AuditedRecord::factory()->create(['name' => 'Test Record', 'active' => true]);
        $record2->delete();

        $query = new TestListQuery;
        $result = $query->findTrashedByName('Test Record');

        $this->assertNotNull($result);
        $this->assertSame($record2->id, $result->id);
    }

    public function test_find_trashed_by_name_returns_null_when_not_found(): void
    {
        AuditedRecord::factory()->create(['name' => 'Other Record', 'active' => true]);

        $query = new TestListQuery;
        $result = $query->findTrashedByName('Test Record');

        $this->assertNull($result);
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
}
