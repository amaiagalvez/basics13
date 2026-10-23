<?php

namespace Basics13\Queries\Users;

use Basics13\Models\Role;
use Basics13\Models\UserType;
use Illuminate\Support\Collection;
use Basics13\Queries\ListQueryBase;
use Illuminate\Database\Eloquent\Model;
use Basics13\Concerns\ChecksPermissions;
use Basics13\Concerns\ResolvesUserModel;
use Illuminate\Database\Eloquent\Builder;
use Basics13\Concerns\DelegatesUserManagement;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Lists user accounts in each of their states, over the model the host configures. The super-admin
 * is never listed, nor counted in the tabs: other users cannot act on it. An actor without
 * `users.view` sees only the accounts it manages as a type admin, set through `visibleTo()`.
 *
 * @extends ListQueryBase<Model>
 *
 * @phpstan-import-type SearchFields from ListQueryBase
 * @phpstan-import-type SearchFilters from ListQueryBase
 */
final class UserListQuery extends ListQueryBase
{
    use ChecksPermissions;
    use DelegatesUserManagement;
    use ResolvesUserModel;

    /**
     * The type admin whose accounts the lists are narrowed to, or null for every account.
     */
    private ?Authenticatable $manager = null;

    /**
     * The list the columns below belong to, named the way the base reads it.
     */
    private const SCOPE_ACTIVE = 'active';

    private const SCOPE_ARCHIVED = 'archived';

    private const SCOPE_TRASHED = 'trashed';

    /**
     * Narrow every list and count for a type admin: everything with `users.view`, only the
     * accounts it manages otherwise.
     */
    public function visibleTo(?Authenticatable $actor): static
    {
        $this->manager = $actor !== null && ! $this->permits($actor, 'users.view')
            && ($this->permits($actor, 'users.view_own') || $this->permits($actor, 'users.manage_own'))
            ? $actor
            : null;

        return $this;
    }

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
            $this->accounts()->onlyTrashed()->with(['roles', 'userTypes'])->latest('deleted_at')->orderBy('id'),
            $search,
            $this->searchColumns(self::SCOPE_TRASHED, ['name', 'email', 'deleted_at']),
            filters: $filters,
            scope: self::SCOPE_TRASHED,
        );
    }

    /**
     * The roles the account form can offer: every one outside the trash, archived ones included so
     * an edit keeps the roles an account already holds. System roles only for the super-admin. A
     * type admin is offered only the roles it could grant (see DelegatesUserManagement).
     *
     * @return Collection<int, Role>
     */
    public function assignableRoles(bool $withSystemRoles, ?Authenticatable $grantor = null): Collection
    {
        return Role::query()
            ->when(! $withSystemRoles, static fn (Builder $query): Builder => $query->where('is_system', false))
            ->when($grantor !== null, fn (Builder $query): Builder => $query->whereIn('id', $this->grantableRoleIds($grantor)))
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * The types the account form can offer: every one outside the trash for whoever assigns types
     * globally, archived ones included so an edit keeps the types an account already holds; only
     * the types it administers for a type admin.
     *
     * @return Collection<int, UserType>
     */
    public function assignableTypes(?Authenticatable $typeAdmin = null): Collection
    {
        return UserType::query()
            ->when($typeAdmin !== null, fn (Builder $query): Builder => $query->whereIn('id', $this->administeredTypeIds($typeAdmin)))
            ->orderBy('name_eu')
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
            'user_type_ids' => [
                'column' => 'BAS_user_types.id',
                'relation' => 'userTypes',
                'label' => 'User types',
                'help' => 'Equals shows the users holding at least one of the selected types; not equals, those holding none of them.',
                'type' => 'select',
                'visible' => true,
                'order' => 10,
                'group' => 'relations',
                'options' => fn (string $scope) => UserType::query()
                    ->where('active', true)
                    ->orderBy('name_eu')
                    ->pluck('name_eu', 'id')
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
     * The columns are unqualified on purpose: the query has no joins and the host may back the
     * configured model with a table that is not called `users`.
     *
     * @return Builder<Model>
     */
    private function ordered(): Builder
    {
        return $this->accounts()->with(['roles', 'userTypes'])->orderBy('name')->orderBy('id');
    }

    /**
     * Every account but the super-admin, or only the ones the type admin manages. An explicit condition rather than a global scope, which
     * would also hide the account from the login.
     *
     * @return Builder<Model>
     */
    private function accounts(): Builder
    {
        $modelClass = $this->userModelClass();
        $query = $modelClass::query()->where('is_super_admin', false);

        return $this->manager === null ? $query : $this->whereManagedBy($query, $this->manager);
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
