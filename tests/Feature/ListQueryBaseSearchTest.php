<?php

namespace Basics13\Tests\Feature;

use Basics13\Tests\TestCase;
use Basics13\Queries\ListQueryBase;
use Basics13\Tests\Fixtures\AuditedRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ListQueryBaseSearchTest extends TestCase
{
    use RefreshDatabase;

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
}
