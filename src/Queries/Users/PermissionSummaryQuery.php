<?php

namespace Basics13\Queries\Users;

use Basics13\Models\Role;
use Basics13\Models\Permission;
use Illuminate\Support\Collection;
use Basics13\Queries\ListQueryBase;
use Illuminate\Support\LazyCollection;
use Illuminate\Database\Eloquent\Model;
use Basics13\Concerns\ResolvesUserModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * The active accounts with the active roles they hold and the permissions of those roles, for the
 * effective-permissions summary and its CSV. The super-admin is left out: it holds everything and
 * other users cannot see it.
 *
 * @extends ListQueryBase<Model>
 *
 * @phpstan-import-type SearchFields from ListQueryBase
 * @phpstan-import-type SearchFilters from ListQueryBase
 */
final class PermissionSummaryQuery extends ListQueryBase
{
    use ResolvesUserModel;

    /**
     * @var non-empty-list<literal-string>
     */
    private const SEARCH_COLUMNS = ['name', 'email'];

    /**
     * @param  SearchFilters  $filters
     * @return LengthAwarePaginator<int, Model>
     */
    public function page(string $search, array $filters = []): LengthAwarePaginator
    {
        return $this->paginate($this->accounts(), $search, self::SEARCH_COLUMNS, $filters);
    }

    /**
     * Every row the same search and filters leave, unpaginated, for the export.
     *
     * @param  SearchFilters  $filters
     * @return LazyCollection<int, Model>
     */
    public function all(string $search, array $filters = []): LazyCollection
    {
        $query = $this->whereMatches($this->accounts(), $search, self::SEARCH_COLUMNS);

        if ($filters !== []) {
            $this->applyFilters($query, $filters, $this->searchableFields('active'), 'active');
        }

        return $query->lazy();
    }

    /**
     * The modules that have permissions, in the order the summary shows them as columns.
     *
     * @return Collection<int, string>
     */
    public function modules(): Collection
    {
        return Permission::query()->distinct()->orderBy('module')->pluck('module');
    }

    /**
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
            'role_ids' => [
                'column' => 'roles.id',
                'relation' => 'roles',
                'label' => 'Roles',
                'help' => 'Equals shows the accounts holding at least one of the selected roles; not equals, those holding none of them.',
                'type' => 'select',
                'visible' => true,
                'order' => 10,
                'group' => 'relations',
                'options' => static fn (string $scope): array => Role::query()
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all(),
                'multiple' => true,
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
     * Accounts have no integration, so the base hook gets a no-op that appends nothing.
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
     * Only active roles grant anything, so only those are loaded. The columns are unqualified: the
     * host may back the configured model with a table that is not called `users`.
     *
     * @return Builder<Model>
     */
    private function accounts(): Builder
    {
        $modelClass = $this->userModelClass();

        return $modelClass::query()
            ->where('active', true)
            ->where('is_super_admin', false)
            ->with(['roles' => static fn (Relation $roles): Relation => $roles
                ->where('roles.active', true)
                ->orderBy('roles.name')
                ->with('permissions')])
            ->orderBy('name')
            ->orderBy('id');
    }
}
