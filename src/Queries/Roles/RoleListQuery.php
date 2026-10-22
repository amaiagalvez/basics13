<?php

namespace Basics13\Queries\Roles;

use Basics13\Models\Role;
use Basics13\Queries\ListQueryBase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * @extends ListQueryBase<Role>
 *
 * @phpstan-import-type SearchFilters from ListQueryBase
 */
final class RoleListQuery extends ListQueryBase
{
    /**
     * @var non-empty-list<literal-string>
     */
    private const SEARCH_COLUMNS = ['roles.name', 'roles.slug', 'roles.description'];

    /**
     * @param  SearchFilters  $filters
     * @return LengthAwarePaginator<int, Role>
     */
    public function active(string $search, array $filters = []): LengthAwarePaginator
    {
        return $this->paginate(
            $this->listed(Role::query()->where('roles.active', true)->orderBy('roles.name')->orderBy('roles.id')),
            $search,
            searchColumns: self::SEARCH_COLUMNS,
            filters: $filters,
            scope: 'active',
        );
    }

    /**
     * @param  SearchFilters  $filters
     * @return LengthAwarePaginator<int, Role>
     */
    public function archived(string $search, array $filters = []): LengthAwarePaginator
    {
        return $this->paginate(
            $this->listed(Role::query()->where('roles.active', false)->orderBy('roles.name')->orderBy('roles.id')),
            $search,
            searchColumns: [...self::SEARCH_COLUMNS, 'roles.updated_at'],
            filters: $filters,
            scope: 'archived',
        );
    }

    /**
     * @param  SearchFilters  $filters
     * @return LengthAwarePaginator<int, Role>
     */
    public function trashed(string $search, array $filters = []): LengthAwarePaginator
    {
        return $this->paginate(
            $this->listed(Role::onlyTrashed()->latest('deleted_at')->orderBy('id')),
            $search,
            searchColumns: [...self::SEARCH_COLUMNS, 'roles.deleted_at'],
            filters: $filters,
            scope: 'trashed',
        );
    }

    /**
     * Roles are a short catalogue: the list search is enough, no advanced filters.
     *
     * @return array<string, array{column: string, label: string, type: string}>
     */
    public function searchableFields(string $scope): array
    {
        return [];
    }

    /**
     * @return class-string<Role>
     */
    protected function getModelClass(): string
    {
        return Role::class;
    }

    /**
     * Roles have no integration, so the base hook gets a no-op that appends nothing.
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
     * The permissions the edit form needs, and how many accounts hold the role, trashed accounts
     * included: those keep the role too, and that count is what the list
     * needs before it offers to delete a role instead of archiving it.
     *
     * @param  Builder<Role>  $query
     * @return Builder<Role>
     */
    private function listed(Builder $query): Builder
    {
        return $query
            ->with('permissions')
            ->withCount([
                'permissions',
                'users' => static fn (Builder $users): Builder => $users->withoutGlobalScope(SoftDeletingScope::class),
            ]);
    }
}
