<?php

namespace Basics13\Queries\UserTypes;

use Basics13\Models\UserType;
use Basics13\Queries\ListQueryBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * @extends ListQueryBase<UserType>
 *
 * @phpstan-import-type SearchFilters from ListQueryBase
 */
final class UserTypeListQuery extends ListQueryBase
{
    /**
     * Search columns with table prefix for the query builder.
     *
     * @var list<string>
     */
    private const NAME_COLUMNS = [
        'BAS_user_types.name_eu',
        'BAS_user_types.name_es',
        'BAS_user_types.name_fr',
        'BAS_user_types.name_en',
    ];

    /**
     * @param  SearchFilters  $filters
     * @return LengthAwarePaginator<int, UserType>
     */
    public function active(string $search, array $filters = []): LengthAwarePaginator
    {
        return $this->paginate(
            $this->withUsersCount(UserType::query()
                ->where('BAS_user_types.active', true)
                ->orderBy('BAS_user_types.name_eu')
                ->orderBy('BAS_user_types.id')),
            $search,
            searchColumns: self::NAME_COLUMNS,
            filters: $filters,
            scope: 'active',
        );
    }

    /**
     * @param  SearchFilters  $filters
     * @return LengthAwarePaginator<int, UserType>
     */
    public function archived(string $search, array $filters = []): LengthAwarePaginator
    {
        return $this->paginate(
            $this->withUsersCount(UserType::query()
                ->where('BAS_user_types.active', false)
                ->orderBy('BAS_user_types.name_eu')
                ->orderBy('BAS_user_types.id')),
            $search,
            searchColumns: [...self::NAME_COLUMNS, 'BAS_user_types.updated_at'],
            filters: $filters,
            scope: 'archived',
        );
    }

    /**
     * @param  SearchFilters  $filters
     * @return LengthAwarePaginator<int, UserType>
     */
    public function trashed(string $search, array $filters = []): LengthAwarePaginator
    {
        return $this->paginate(
            $this->withUsersCount(UserType::onlyTrashed()
                ->latest('deleted_at')
                ->orderBy('id')),
            $search,
            searchColumns: [...self::NAME_COLUMNS, 'BAS_user_types.deleted_at'],
            filters: $filters,
            scope: 'trashed',
        );
    }

    /**
     * Get the searchable field definitions for the current scope.
     *
     * @param  string  $scope  One of 'active', 'archived', 'trashed'
     * @return array<string, array{column: string, label: string, type: string, operators?: array<int,string>, default_operator?: string, visible?: bool, order?: int, group?: string, nullable?: bool, placeholder?: string, help?: string, condition?: callable, options?: array<int,string>|callable, multiple?: bool}>
     */
    public function searchableFields(string $scope): array
    {
        // User types are a short catalogue: the list search is enough, no advanced filters.
        return [];
    }

    /**
     * @return class-string<UserType>
     */
    protected function getModelClass(): string
    {
        return UserType::class;
    }

    /**
     * User types have no integration, so the base hook gets a no-op that appends nothing.
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

    /**
     * A trashed type is found by its euskera name, which is the one the create form names first and
     * the only name a type is required to have.
     *
     * @param  Builder<UserType>  $query
     */
    protected function firstTrashedByName(Builder $query, string $name): ?Model
    {
        return $query->where('name_eu', $name)->latest('deleted_at')->first();
    }

    /**
     * How many accounts hold the type, deleted ones included: a type in the trash keeps the pivot
     * rows of the accounts that held it, and that count is what the list needs to know before it
     * offers to delete a type instead of archiving it.
     *
     * @param  Builder<UserType>  $query
     * @return Builder<UserType>
     */
    private function withUsersCount(Builder $query): Builder
    {
        return $query->withCount([
            'users' => fn (Builder $users): Builder => $users
                ->withoutGlobalScope(SoftDeletingScope::class),
        ]);
    }
}
