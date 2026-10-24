<?php

namespace Basics13\Queries\EmailConfigurations;

use Basics13\Queries\ListQueryBase;
use Basics13\Models\EmailConfiguration;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends ListQueryBase<EmailConfiguration>
 *
 * @phpstan-import-type SearchFilters from ListQueryBase
 */
final class EmailConfigurationListQuery extends ListQueryBase
{
    /**
     * @var non-empty-list<literal-string>
     */
    private const SEARCH_COLUMNS = ['BAS_email_configurations.name', 'BAS_email_configurations.host', 'BAS_email_configurations.sender_email'];

    /**
     * @param  SearchFilters  $filters
     * @return LengthAwarePaginator<int, EmailConfiguration>
     */
    public function active(string $search, array $filters = []): LengthAwarePaginator
    {
        return $this->paginate(
            EmailConfiguration::query()->where('BAS_email_configurations.active', true)->orderByDesc('BAS_email_configurations.default')->orderBy('BAS_email_configurations.name')->orderBy('BAS_email_configurations.id'),
            $search,
            searchColumns: self::SEARCH_COLUMNS,
            filters: $filters,
            scope: 'active',
        );
    }

    /**
     * @param  SearchFilters  $filters
     * @return LengthAwarePaginator<int, EmailConfiguration>
     */
    public function archived(string $search, array $filters = []): LengthAwarePaginator
    {
        return $this->paginate(
            EmailConfiguration::query()->where('BAS_email_configurations.active', false)->orderBy('BAS_email_configurations.name')->orderBy('BAS_email_configurations.id'),
            $search,
            searchColumns: [...self::SEARCH_COLUMNS, 'BAS_email_configurations.updated_at'],
            filters: $filters,
            scope: 'archived',
        );
    }

    /**
     * @param  SearchFilters  $filters
     * @return LengthAwarePaginator<int, EmailConfiguration>
     */
    public function trashed(string $search, array $filters = []): LengthAwarePaginator
    {
        return $this->paginate(
            EmailConfiguration::onlyTrashed()->latest('deleted_at')->orderBy('id'),
            $search,
            searchColumns: [...self::SEARCH_COLUMNS, 'BAS_email_configurations.deleted_at'],
            filters: $filters,
            scope: 'trashed',
        );
    }

    /**
     * SMTP configurations are a short catalogue: the list search is enough, no advanced filters.
     *
     * @return array<string, array{column: string, label: string, type: string}>
     */
    public function searchableFields(string $scope): array
    {
        return [];
    }

    /**
     * @return class-string<EmailConfiguration>
     */
    protected function getModelClass(): string
    {
        return EmailConfiguration::class;
    }

    /**
     * SMTP configurations have no integration, so the base hook gets a no-op that appends nothing.
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
