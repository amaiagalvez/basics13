<?php

namespace Basics13\Queries\Users;

use Illuminate\Support\Collection;
use Basics13\Queries\ListQueryBase;
use Illuminate\Database\Eloquent\Model;
use Basics13\Concerns\ChecksPermissions;
use Basics13\Concerns\ResolvesUserModel;
use Illuminate\Database\Eloquent\Builder;
use Basics13\Concerns\DelegatesUserManagement;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Options for a remote selector: the active accounts, capped at the shared page size. The
 * super-admin is never offered.
 *
 * @extends ListQueryBase<Model>
 */
final class UserSelectOptionsQuery extends ListQueryBase
{
    use ChecksPermissions;
    use DelegatesUserManagement;
    use ResolvesUserModel;

    /**
     * The type admin whose accounts are offered, or null for every account.
     */
    private ?Authenticatable $manager = null;

    /**
     * Narrow the accounts to what a type admin may see: everything with `users.view`, only the
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
     * @return Collection<int, array{id: int, text: string}>
     */
    public function search(string $search): Collection
    {
        $pattern = $this->searchPattern($search);
        $modelClass = $this->userModelClass();

        $query = $modelClass::query();

        if ($this->manager !== null) {
            $this->whereManagedBy($query, $this->manager);
        }

        return $query
            ->where('active', true)
            ->where('is_super_admin', false)
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $query) => $query
                    ->whereRaw('name like ? escape \''.self::LIKE_ESCAPE.'\'', [$pattern])
                    ->orWhereRaw('email like ? escape \''.self::LIKE_ESCAPE.'\'', [$pattern]),
            ))
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::PER_PAGE)
            ->get(['id', 'name'])
            ->map(static fn (Model $user): array => [
                'id' => (int) $user->getKey(),
                'text' => (string) $user->getAttribute('name'),
            ]);
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

    public function searchableFields(string $scope): array
    {
        return [
            'name' => [
                'column' => 'name',
                'label' => 'basics13::messages.name',
                'type' => 'text',
                'operators' => ['contains', 'equals', 'starts_with', 'ends_with'],
                'default_operator' => 'contains',
                'visible' => true,
                'order' => 10,
                'group' => 'basic',
            ],
            'email' => [
                'column' => 'email',
                'label' => 'basics13::messages.email',
                'type' => 'text',
                'operators' => ['contains', 'equals', 'starts_with', 'ends_with'],
                'default_operator' => 'contains',
                'visible' => true,
                'order' => 20,
                'group' => 'basic',
            ],
        ];
    }
}
