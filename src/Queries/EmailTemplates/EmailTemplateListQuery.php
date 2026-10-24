<?php

namespace Basics13\Queries\EmailTemplates;

use Basics13\Models\EmailTemplate;
use Basics13\Queries\ListQueryBase;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends ListQueryBase<EmailTemplate>
 *
 * @phpstan-import-type SearchFilters from ListQueryBase
 */
final class EmailTemplateListQuery extends ListQueryBase
{
    /**
     * @var non-empty-list<literal-string>
     */
    private const SEARCH_COLUMNS = ['BAS_email_templates.name', 'BAS_email_templates.subject_en'];

    /**
     * @param  SearchFilters  $filters
     * @return LengthAwarePaginator<int, EmailTemplate>
     */
    public function active(string $search, array $filters = []): LengthAwarePaginator
    {
        return $this->paginate(
            EmailTemplate::query()->where('BAS_email_templates.active', true)->orderByDesc('BAS_email_templates.default')->orderBy('BAS_email_templates.name')->orderBy('BAS_email_templates.id'),
            $search,
            searchColumns: self::SEARCH_COLUMNS,
            filters: $filters,
            scope: 'active',
        );
    }

    /**
     * @param  SearchFilters  $filters
     * @return LengthAwarePaginator<int, EmailTemplate>
     */
    public function archived(string $search, array $filters = []): LengthAwarePaginator
    {
        return $this->paginate(
            EmailTemplate::query()->where('BAS_email_templates.active', false)->orderBy('BAS_email_templates.name')->orderBy('BAS_email_templates.id'),
            $search,
            searchColumns: [...self::SEARCH_COLUMNS, 'BAS_email_templates.updated_at'],
            filters: $filters,
            scope: 'archived',
        );
    }

    /**
     * @param  SearchFilters  $filters
     * @return LengthAwarePaginator<int, EmailTemplate>
     */
    public function trashed(string $search, array $filters = []): LengthAwarePaginator
    {
        return $this->paginate(
            EmailTemplate::onlyTrashed()->latest('deleted_at')->orderBy('id'),
            $search,
            searchColumns: [...self::SEARCH_COLUMNS, 'BAS_email_templates.deleted_at'],
            filters: $filters,
            scope: 'trashed',
        );
    }

    /**
     * Email templates are a short catalogue: the list search is enough, no advanced filters.
     *
     * @return array<string, array{column: string, label: string, type: string}>
     */
    public function searchableFields(string $scope): array
    {
        return [];
    }

    /**
     * @return class-string<EmailTemplate>
     */
    protected function getModelClass(): string
    {
        return EmailTemplate::class;
    }

    /**
     * Email templates have no integration, so the base hook gets a no-op that appends nothing.
     */
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
