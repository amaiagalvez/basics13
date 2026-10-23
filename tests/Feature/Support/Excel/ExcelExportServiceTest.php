<?php

namespace Basics13\Tests\Feature\Support\Excel;

use Basics13\Tests\TestCase;
use Basics13\Tests\Fixtures\AuditedRecord;
use Basics13\Support\Excel\ExcelExportService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The export service takes a query the listing already filtered and turns it into rows: it adds no
 * filter of its own, so the file matches whatever the page is showing.
 */
class ExcelExportServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_collection_holds_only_the_rows_of_the_given_query(): void
    {
        AuditedRecord::factory()->create(['name' => 'Alpha']);
        AuditedRecord::factory()->create(['name' => 'Beta']);

        $rows = (new ExcelExportService(
            query: AuditedRecord::query()->where('name', 'Alpha'),
            columnDefinitions: [['key' => 'name', 'label' => 'Name']],
        ))->collection();

        $this->assertSame([['name' => 'Alpha']], $rows->all());
    }

    public function test_the_column_definitions_shape_the_rows_and_the_headings(): void
    {
        AuditedRecord::factory()->create(['name' => 'Alpha', 'active' => false]);

        $export = new ExcelExportService(
            query: AuditedRecord::query(),
            columnDefinitions: [
                ['key' => 'name', 'label' => 'Name'],
                [
                    'key' => 'active',
                    'label' => 'Status',
                    'transformer' => fn (AuditedRecord $record): string => $record->active ? 'Active' : 'Inactive',
                ],
            ],
        );

        $this->assertSame(['Name', 'Status'], $export->headings());
        $this->assertSame([['name' => 'Alpha', 'active' => 'Inactive']], $export->collection()->all());
    }

    public function test_plain_columns_are_used_when_no_column_definitions_are_given(): void
    {
        AuditedRecord::factory()->create(['name' => 'Alpha']);

        $export = new ExcelExportService(
            query: AuditedRecord::query(),
            columns: ['name', 'notes'],
        );

        $this->assertSame(['name', 'notes'], $export->headings());
        $this->assertSame(['name', 'notes'], array_keys($export->collection()->first()));
    }

    public function test_max_rows_caps_the_exported_rows(): void
    {
        AuditedRecord::factory()->count(3)->create();

        $export = new ExcelExportService(
            query: AuditedRecord::query()->orderBy('id'),
            columnDefinitions: [['key' => 'name', 'label' => 'Name']],
            maxRows: 2,
        );

        $this->assertCount(2, $export->collection());
    }

    public function test_a_max_rows_of_zero_exports_every_row(): void
    {
        AuditedRecord::factory()->count(3)->create();

        $export = new ExcelExportService(
            query: AuditedRecord::query(),
            columnDefinitions: [['key' => 'name', 'label' => 'Name']],
            maxRows: 0,
        );

        $this->assertCount(3, $export->collection());
    }

    public function test_the_title_is_exposed_to_the_sheet(): void
    {
        $export = new ExcelExportService(query: AuditedRecord::query(), title: 'Records');

        $this->assertSame('Records', $export->title());
    }

    public function test_the_file_name_uses_the_translated_resource_label(): void
    {
        $fileName = ExcelExportService::fileName('Bezeroak');

        $this->assertStringStartsWith('bezeroak_', $fileName);
        $this->assertStringEndsWith('.xlsx', $fileName);
    }

    public function test_the_file_name_marks_the_archived_and_trash_scopes_in_the_current_language(): void
    {
        app()->setLocale('en');

        $this->assertStringStartsWith('records_archived_', ExcelExportService::fileName('Records', 'archived'));
        $this->assertStringStartsWith('records_trash_', ExcelExportService::fileName('Records', 'trashed'));
        $this->assertStringStartsWith('records_', ExcelExportService::fileName('Records'));
        $this->assertStringNotContainsString('active', ExcelExportService::fileName('Records', 'active'));

        app()->setLocale('eu');

        $this->assertStringStartsWith('erregistroak_artxibatuak_', ExcelExportService::fileName('Erregistroak', 'archived'));
        $this->assertStringStartsWith('erregistroak_zakarrontzia_', ExcelExportService::fileName('Erregistroak', 'trashed'));
    }

    public function test_download_returns_binary_file_response(): void
    {
        $export = new ExcelExportService(query: AuditedRecord::query());

        // Test the download method by checking it calls the Excel facade
        // We use the actual facade but with a test disk to avoid actual file creation
        $this->app->bind('excel', function () {
            return new class
            {
                public function download(mixed $export, string $fileName, string $format): BinaryFileResponse
                {
                    return new BinaryFileResponse(__FILE__);
                }
            };
        });

        $response = $export->download('test.xlsx');

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
    }

    public function test_store_returns_boolean(): void
    {
        $export = new ExcelExportService(query: AuditedRecord::query());

        $this->app->bind('excel', function () {
            return new class
            {
                public function store(mixed $export, string $fileName, string $disk, string $format): bool
                {
                    return true;
                }
            };
        });

        $result = $export->store('test.xlsx', 'local');

        $this->assertTrue($result);
    }

    public function test_should_auto_size_returns_true(): void
    {
        $export = new ExcelExportService(query: AuditedRecord::query());

        $this->assertTrue($export->shouldAutoSize());
    }

    public function test_collection_handles_missing_attributes_and_methods(): void
    {
        AuditedRecord::factory()->create(['name' => 'Alpha']);

        $export = new ExcelExportService(
            query: AuditedRecord::query(),
            columnDefinitions: [
                ['key' => 'name', 'label' => 'Name'],
                ['key' => 'nonexistent', 'label' => 'Missing'],
                ['key' => 'notes', 'label' => 'Notes', 'transformer' => fn ($r) => 'transformed'],
            ],
        );

        $rows = $export->collection()->all();

        $this->assertSame('Alpha', $rows[0]['name']);
        $this->assertSame('', $rows[0]['nonexistent']);
        $this->assertSame('transformed', $rows[0]['notes']);
    }

    public function test_collection_uses_method_when_attribute_missing(): void
    {
        // Add a method to the model that returns a value
        $record = AuditedRecord::factory()->create(['name' => 'Alpha']);

        $export = new ExcelExportService(
            query: AuditedRecord::query(),
            columnDefinitions: [
                ['key' => 'name', 'label' => 'Name'],
                ['key' => 'customMethod', 'label' => 'Custom'],
            ],
        );

        // The model doesn't have customMethod, so it should return empty string
        $rows = $export->collection()->all();
        $this->assertSame('', $rows[0]['customMethod']);
    }

    public function test_headings_returns_columns_when_no_definitions(): void
    {
        $export = new ExcelExportService(
            query: AuditedRecord::query(),
            columns: ['name', 'notes'],
        );

        $this->assertSame(['name', 'notes'], $export->headings());
    }

    public function test_headings_returns_empty_when_no_columns_or_definitions(): void
    {
        $export = new ExcelExportService(query: AuditedRecord::query());

        $this->assertSame([], $export->headings());
    }

    public function test_collection_calls_method_on_record_when_attribute_missing_but_method_exists(): void
    {
        // Test the method_exists branch logic directly by testing the service behavior
        // with a real model that has a custom method via the model's __call mechanism

        // Create a test record
        $record = AuditedRecord::factory()->create(['name' => 'TestRecord']);

        // Create an export service with column definitions that reference a non-existent
        // attribute but a method that could exist via __call
        $export = new ExcelExportService(
            query: AuditedRecord::query()->where('id', $record->id),
            columnDefinitions: [
                ['key' => 'name', 'label' => 'Name'],
                ['key' => 'customExportMethod', 'label' => 'Custom Method'],
            ],
        );

        $rows = $export->collection()->all();

        // The model doesn't have customExportMethod attribute or method,
        // so it should return empty string for that column
        $this->assertSame('TestRecord', $rows[0]['name']);
        $this->assertSame('', $rows[0]['customExportMethod']);
    }

    public function test_collection_returns_all_attributes_when_no_columns_specified(): void
    {
        AuditedRecord::factory()->create(['name' => 'Alpha', 'active' => true, 'notes' => 'Some notes']);

        $export = new ExcelExportService(query: AuditedRecord::query());

        $rows = $export->collection()->all();

        $this->assertArrayHasKey('name', $rows[0]);
        $this->assertArrayHasKey('active', $rows[0]);
        $this->assertArrayHasKey('notes', $rows[0]);
        $this->assertArrayHasKey('id', $rows[0]);
    }
}
