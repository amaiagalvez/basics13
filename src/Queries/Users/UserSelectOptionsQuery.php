<?php

namespace Basics13\Queries\Users;

use Illuminate\Support\Collection;
use Basics13\Queries\ListQueryBase;
use Illuminate\Database\Eloquent\Model;
use Basics13\Concerns\ResolvesUserModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * Options for a remote selector: the active accounts, capped at the shared page size.
 *
 * @extends ListQueryBase<Model>
 */
final class UserSelectOptionsQuery extends ListQueryBase
{
    use ResolvesUserModel;

    /**
     * @return Collection<int, array{id: int, text: string}>
     */
    public function search(string $search): Collection
    {
        $pattern = $this->searchPattern($search);
        $modelClass = $this->userModelClass();

        return $modelClass::query()
            ->where('active', true)
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
}
