<?php

namespace Basics13\Queries\Users;

use Basics13\Models\Role;
use Illuminate\Support\Collection;
use Basics13\Queries\ListQueryBase;
use Illuminate\Database\Eloquent\Model;
use Basics13\Concerns\ResolvesUserModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Lists user accounts in each of their states, over the model the host configures. The super-admin
 * is never listed, nor counted in the tabs: other users cannot act on it.
 *
 * @extends ListQueryBase<Model>
 *
 * @phpstan-import-type SearchFields from ListQueryBase
 * @phpstan-import-type SearchFilters from ListQueryBase
 */
final class UserListQuery extends ListQueryBase
{
    use ResolvesUserModel;

    /**
     * The list the columns below belong to, named the way the base reads it.
     */
    private const SCOPE_ACTIVE = 'active';

    private const SCOPE_ARCHIVED = 'archived';

    private const SCOPE_TRASHED = 'trashed';

    /**
     * @param  SearchFilters  $filters
     * @return LengthAwarePaginator<int, Model>
     */
    public function active(string $search, array $filters = []): LengthAwarePaginator
    {
        return $this->paginate(
            $this->ordered()->where('active', true),
            $search,
            $this->searchColumns(self::SCOPE_ACTIVE, ['name', 'email']),
            filters: $filters,
            scope: self::SCOPE_ACTIVE,
        );
    }

    /**
     * @param  SearchFilters  $filters
     * @return LengthAwarePaginator<int, Model>
     */
    public function archived(string $search, array $filters = []): LengthAwarePaginator
    {
        return $this->paginate(
            $this->ordered()->where('active', false),
            $search,
            $this->searchColumns(self::SCOPE_ARCHIVED, ['name', 'email', 'updated_at']),
            filters: $filters,
            scope: self::SCOPE_ARCHIVED,
        );
    }

    /**
     * @param  SearchFilters  $filters
     * @return LengthAwarePaginator<int, Model>
     */
    public function trashed(string $search, array $filters = []): LengthAwarePaginator
    {
        return $this->paginate(
            // The configured model is soft deletable. That scope is invisible through the shared
            // `Model` base, hence the ignore.
            /** @phpstan-ignore method.notFound */
            $this->accounts()->onlyTrashed()->with('roles')->latest('deleted_at')->orderBy('id'),
            $search,
            $this->searchColumns(self::SCOPE_TRASHED, ['name', 'email', 'deleted_at']),
            filters: $filters,
            scope: self::SCOPE_TRASHED,
        );
    }

    /**
     * The roles the account form can offer: every one outside the trash, archived ones included so
     * an edit keeps the roles an account already holds. System roles only for the super-admin.
     *
     * @return Collection<int, Role>
     */
    public function assignableRoles(bool $withSystemRoles): Collection
    {
        return Role::query()
            ->when(! $withSystemRoles, static fn (Builder $query): Builder => $query->where('is_system', false))
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{active: int, archived: int, trashed: int}
     */
    public function stateCounts(
        ?int $activeTotal = null,
        ?int $archivedTotal = null,
        ?int $trashedTotal = null,
    ): array {
        return [
            'active' => $activeTotal ?? $this->accounts()->where('active', true)->count(),
            'archived' => $archivedTotal ?? $this->accounts()->where('active', false)->count(),
            /** @phpstan-ignore method.notFound */
            'trashed' => $trashedTotal ?? $this->accounts()->onlyTrashed()->count(),
        ];
    }

    /**
     * Get the searchable field definitions for the current scope.
     *
     * @param  string  $scope  One of 'active', 'archived', 'trashed'
     * @return SearchFields
     */
    public function searchableFields(string $scope): array
    {
        return [
            'name' => [
                'column' => 'name',
                'label' => 'Name',
                'type' => 'text',
                'visible' => true,
                'order' => 10,
                'group' => 'basic',
            ],
            'email' => [
                'column' => 'email',
                'label' => 'Email',
                'type' => 'text',
                'visible' => true,
                'order' => 20,
                'group' => 'basic',
            ],
            // Only the trash mixes active and archived records: on the active and archived lists
            // the status filter could only return everything or nothing.
            ...($scope === 'trashed' ? ['active' => [
                'column' => 'active',
                'label' => 'Status',
                'type' => 'boolean',
                'visible' => true,
                'order' => 10,
                'group' => 'status',
            ]] : []),
            'created_at' => [
                'column' => 'created_at',
                'label' => 'basics13::messages.created_at',
                'type' => 'date',
                'visible' => true,
                'order' => 20,
                'group' => 'dates',
            ],
        ];
    }

    /**
     * @return class-string<Model>
     */
    protected function getModelClass(): string
    {
        return $this->userModelClass();
    }

    /**
     * The columns are unqualified on purpose: the query has no joins and the host may back the
     * configured model with a table that is not called `users`.
     *
     * @return Builder<Model>
     */
    private function ordered(): Builder
    {
        return $this->accounts()->with('roles')->orderBy('name')->orderBy('id');
    }

    /**
     * Every account but the super-admin. An explicit condition rather than a global scope, which
     * would also hide the account from the login.
     *
     * @return Builder<Model>
     */
    private function accounts(): Builder
    {
        $modelClass = $this->userModelClass();

        return $modelClass::query()->where('is_super_admin', false);
    }

    /**
     * Accounts have no integration: nothing outside the package knows a user beyond the columns the
     * table already has. The base still asks for one, so it gets a no-op that appends nothing.
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
