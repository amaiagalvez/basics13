<?php

namespace Basics13\Queries\UserTypes;

use Basics13\Models\UserType;
use Illuminate\Support\Collection;
use Basics13\Queries\ListQueryBase;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends ListQueryBase<UserType>
 */
final class UserTypeSelectOptionsQuery extends ListQueryBase
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
     * Options for the account form select. Euskera is the text every option shows, while the term
     * the user types is matched against all four languages, so a type can be found by any of the
     * names it has.
     *
     * @return Collection<int, array{id: int, text: string}>
     */
    public function search(string $search): Collection
    {
        $query = UserType::query()
            ->where('active', true)
            ->orderBy('BAS_user_types.name_eu')
            ->orderBy('BAS_user_types.id');

        if ($search !== '') {
            $pattern = $this->searchPattern($search);

            $query->where(function (Builder $query) use ($pattern): void {
                foreach (self::NAME_COLUMNS as $column) {
                    $query->orWhereRaw($column.' LIKE ? ESCAPE \''.self::LIKE_ESCAPE.'\'', [$pattern]);
                }
            });
        }

        return $query->limit(self::PER_PAGE)
            ->get(['id', 'name_eu'])
            ->map(fn (UserType $userType): array => [
                'id' => $userType->id,
                'text' => $userType->name_eu,
            ]);
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
     * Select-option queries do not expose list filters.
     *
     * @return array{}
     */
    public function searchableFields(string $scope): array
    {
        return [];
    }
}
