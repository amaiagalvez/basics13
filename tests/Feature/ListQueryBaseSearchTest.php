<?php

namespace Basics13\Tests\Feature;

use Basics13\Tests\TestCase;
use Basics13\Queries\ListQueryBase;
use Basics13\Tests\Fixtures\AuditedRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

class ListQueryBaseSearchTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_search_matches_any_column_and_excludes_unrelated_records(): void
    {
        $nameMatch = AuditedRecord::factory()->create(['name' => 'Search in name', 'notes' => 'Other notes']);
        $notesMatch = AuditedRecord::factory()->create(['name' => 'Other name', 'notes' => 'Search in notes']);
        AuditedRecord::factory()->create(['name' => 'Unrelated', 'notes' => 'Other notes']);

        $paginator = (new SearchListQuery)->search('Search', ['name', 'notes']);

        $this->assertSame([$nameMatch->id, $notesMatch->id], $paginator->getCollection()->modelKeys());
        $this->assertSame(2, $paginator->total());
        $this->assertSame(25, $paginator->perPage());
        $this->assertStringContainsString('search=Search', $paginator->url(2));
    }

    #[DataProvider('literalSearches')]
    public function test_search_treats_like_metacharacters_as_literal_text(string $search, string $decoy): void
    {
        $match = AuditedRecord::factory()->create(['name' => 'Prefix '.$search.' suffix', 'notes' => 'Other notes']);
        AuditedRecord::factory()->create(['name' => 'Prefix '.$decoy.' suffix', 'notes' => 'Other notes']);

        $paginator = (new SearchListQuery)->search($search, ['name']);

        $this->assertSame([$match->id], $paginator->getCollection()->modelKeys());
        $this->assertSame(1, $paginator->total());
    }

    /** @return array<string, array{string, string}> */
    public static function literalSearches(): array
    {
        return [
            'percent' => ['100%', '1000'],
            'underscore' => ['a_b', 'acb'],
            'escape character' => ['!tag', 'tag'],
            'combined metacharacters' => ['a%_b!c', 'aXXb!c'],
        ];
    }

    public function test_find_trashed_by_name_returns_latest_soft_deleted_record(): void
    {
        $activeRecord = AuditedRecord::factory()->create(['name' => 'Test Record Active']);
        $trashed1 = AuditedRecord::factory()->create(['name' => 'Test Record 1']);
        $trashed1->update(['name' => 'Test Record']);
        $trashed1->delete();
        $trashed2 = AuditedRecord::factory()->create(['name' => 'Test Record 2']);
        $trashed2->update(['name' => 'Test Record']);
        $trashed2->delete();

        $query = new FindTrashedListQuery;
        $found = $query->findTrashedByName('Test Record');

        $this->assertNotNull($found);
        $this->assertSame($trashed2->id, $found->id);
        $this->assertNotNull($found->deleted_at);
    }

    public function test_find_trashed_by_name_returns_null_when_no_match(): void
    {
        AuditedRecord::factory()->create(['name' => 'Other Record Active']);
        $trashed = AuditedRecord::factory()->create(['name' => 'Other Record 1']);
        $trashed->update(['name' => 'Other Record']);
        $trashed->delete();

        $query = new FindTrashedListQuery;
        $found = $query->findTrashedByName('Non Existent');

        $this->assertNull($found);
    }

    public function test_find_trashed_by_name_scopes_by_project_id(): void
    {
        // This test would need a project_id column on the model; AuditedRecord doesn't have one.
        // The method accepts the parameter but the test model doesn't support it.
        // Skipping detailed project_id test since the model doesn't have the column.
        $this->markTestSkipped('AuditedRecord fixture does not have project_id column.');
    }

    public function test_state_counts_returns_correct_counts(): void
    {
        // Create active records with unique names
        AuditedRecord::factory()->create(['name' => 'Active 1', 'active' => true]);
        AuditedRecord::factory()->create(['name' => 'Active 2', 'active' => true]);
        AuditedRecord::factory()->create(['name' => 'Active 3', 'active' => true]);

        // Create archived records with unique names
        AuditedRecord::factory()->create(['name' => 'Archived 1', 'active' => false]);
        AuditedRecord::factory()->create(['name' => 'Archived 2', 'active' => false]);

        // Create and delete trashed records
        $trashed = AuditedRecord::factory()->count(4)->create(['active' => true]);
        foreach ($trashed as $index => $record) {
            $record->update(['name' => "Trashed Record {$index}"]);
            $record->delete();
        }

        $query = new FindTrashedListQuery;
        $counts = $query->stateCounts();

        $this->assertSame(3, $counts['active']);
        $this->assertSame(2, $counts['archived']);
        $this->assertSame(4, $counts['trashed']);
    }

    public function test_state_counts_accepts_precomputed_totals(): void
    {
        $query = new FindTrashedListQuery;
        $counts = $query->stateCounts(10, 5, 2);

        $this->assertSame(10, $counts['active']);
        $this->assertSame(5, $counts['archived']);
        $this->assertSame(2, $counts['trashed']);
    }
}

/** @extends ListQueryBase<AuditedRecord> */
class SearchListQuery extends ListQueryBase
{
    /**
     * @param  non-empty-list<literal-string>  $columns
     * @return LengthAwarePaginator<int, AuditedRecord>
     */
    public function search(string $search, array $columns): LengthAwarePaginator
    {
        return $this->paginate(AuditedRecord::query()->orderBy('id'), $search, $columns);
    }

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

/** @extends ListQueryBase<AuditedRecord> */
class FindTrashedListQuery extends ListQueryBase
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
