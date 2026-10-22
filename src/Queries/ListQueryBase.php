<?php

namespace Basics13\Queries;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @template TModel of Model
 *
 * @phpstan-type SearchField array{
 *     column: string,
 *     label: string,
 *     type: string,
 *     operators?: list<string>,
 *     default_operator?: string,
 *     visible?: bool,
 *     order?: int,
 *     group?: string,
 *     nullable?: bool,
 *     placeholder?: string,
 *     help?: string,
 *     condition?: callable(string): bool,
 *     options?: array<int, string>|callable(string): array<int, string>,
 *     multiple?: bool,
 *     relation?: string
 * }
 * @phpstan-type SearchFields array<string, SearchField>
 * @phpstan-type SearchFilters array<string, array{operator: string, value: mixed}>
 *
 * @method array<literal-string> searchColumns(string $scope, array<literal-string> $columns)
 */
abstract class ListQueryBase
{
    /**
     * Rows per list page. The remote select-options queries cap their result set at the same number
     * on purpose, so both limits move together; give the selects their own constant if they ever
     * need to differ, since no test covers that cap.
     */
    public const PER_PAGE = 25;

    /**
     * Escape character declared in every LIKE pattern, so a search for `%`, `_` or a
     * backslash behaves the same on every engine and does not depend on the server's
     * implicit escape character or on its sql_mode.
     */
    protected const LIKE_ESCAPE = '!';

    /**
     * @param  Builder<TModel>  $query
     * @param  non-empty-list<literal-string>  $searchColumns
     * @param  SearchFilters  $filters
     * @return LengthAwarePaginator<int, TModel>
     */
    protected function paginate(
        Builder $query,
        string $search,
        array $searchColumns,
        array $filters = [],
        string $scope = 'active',
    ): LengthAwarePaginator {
        $this->whereMatches($query, $search, $searchColumns);

        if ($filters !== []) {
            $searchableFields = $this->searchableFields($scope);
            $this->applyFilters($query, $filters, $searchableFields, $scope);
        }

        $items = $query->paginate(static::PER_PAGE);

        if ($search !== '') {
            $items->appends(['search' => $search]);
        }

        if ($filters !== []) {
            $items->appends(['filters' => $filters]);
        }

        return $items;
    }

    /**
     * Narrow a query down to the records that match a search term in any of the given columns. A
     * blank term leaves the query untouched, so a caller can hand the request value straight in.
     * Every term reaches the database through the shared pattern.
     *
     * The model is generic in its own right rather than taken from the class template: a query that
     * spans more than one resource still gets its own builder type back, so the caller keeps
     * working with concrete models.
     *
     * @template TQueryModel of Model
     *
     * @param  Builder<TQueryModel>  $query
     * @param  non-empty-list<literal-string>  $searchColumns
     * @return Builder<TQueryModel>
     */
    protected function whereMatches(Builder $query, string $search, array $searchColumns): Builder
    {
        if ($search === '') {
            return $query;
        }

        $pattern = $this->searchPattern($search);
        $clause = ' like ? escape \''.self::LIKE_ESCAPE.'\'';

        return $query->where(function (Builder $query) use ($pattern, $clause, $searchColumns): void {
            foreach ($searchColumns as $column) {
                $query->orWhereRaw($column.$clause, [$pattern]);
            }
        });
    }

    /**
     * Apply advanced filters to the query based on field configuration.
     *
     * @param  Builder<TModel>  $query
     * @param  SearchFilters  $filters
     * @param  SearchFields  $fields
     */
    protected function applyFilters(Builder $query, array $filters, array $fields, string $scope): void
    {
        foreach ($filters as $fieldKey => $filter) {
            $field = $fields[$fieldKey] ?? null;
            if (! $field) {
                continue;
            }

            $operator = $filter['operator'];
            $value = $filter['value'];

            // Only the operators the field declares; an empty boolean operator means "any".
            $operators = $field['operators'] ?? self::getDefaultOperatorsForType($field['type'], $field['nullable'] ?? false);
            if (! in_array($operator, $operators, true)) {
                continue;
            }

            if ($value === '' || $value === [] || $value === null) {
                // Allow is_empty operator and boolean fields to pass through with null/empty
                if ($operator !== 'is_empty' && $field['type'] !== 'boolean') {
                    continue;
                }
            }

            $column = $field['column'];

            if (isset($field['relation'])) {
                $this->applyRelationFilter($query, $field['relation'], $column, $operator, $value);

                continue;
            }

            $type = $field['type'];
            $isMultiSelect = ($field['multiple'] ?? true) && $type === 'select';
            $nullable = $field['nullable'] ?? false;

            $this->applyFilterOperator($query, $column, $operator, $value, $type, $isMultiSelect, $nullable);
        }
    }

    /**
     * Apply a single filter operator to the query.
     *
     * @param  Builder<TModel>  $query
     */
    protected function applyFilterOperator(
        Builder $query,
        string $column,
        string $operator,
        mixed $value,
        string $type,
        bool $isMultiSelect,
        bool $nullable
    ): void {
        switch ($type) {
            case 'text':
                $this->applyTextFilter($query, $column, $operator, $value);
                break;
            case 'date':
                $this->applyDateFilter($query, $column, $operator, $value);
                break;
            case 'boolean':
                $this->applyBooleanFilter($query, $column, $operator, $value);
                break;
            case 'number':
                $this->applyNumberFilter($query, $column, $operator, $value);
                break;
            case 'select':
                $this->applySelectFilter($query, $column, $operator, $value, $isMultiSelect, $nullable);
                break;
        }
    }

    /**
     * Apply text filter operators.
     *
     * @param  Builder<TModel>  $query
     */
    protected function applyTextFilter(Builder $query, string $column, string $operator, mixed $value): void
    {
        if (! is_scalar($value) || $value === '') {
            return;
        }

        $escapedValue = $this->escapeLikeValue((string) $value);
        $pattern = match ($operator) {
            'contains' => "%{$escapedValue}%",
            'starts_with' => "{$escapedValue}%",
            'ends_with' => "%{$escapedValue}",
            default => null,
        };

        if ($pattern !== null) {
            // Same clause as whereMatches: the escaped pattern only means what it says once the
            // escape character is declared. The column comes from the server-side field definitions.
            /** @phpstan-ignore argument.type */
            $query->whereRaw($column.' like ? escape \''.self::LIKE_ESCAPE.'\'', [$pattern]);

            return;
        }

        match ($operator) {
            'equals' => $query->where($column, '=', $value),
            'not_equals' => $query->where($column, '!=', $value),
            default => null,
        };
    }

    /**
     * Apply date filter operators.
     *
     * @param  Builder<TModel>  $query
     */
    protected function applyDateFilter(Builder $query, string $column, string $operator, mixed $value): void
    {
        $single = is_string($value) && $value !== '';

        match ($operator) {
            'equals' => $single ? $query->whereDate($column, '=', $value) : null,
            'before' => $single ? $query->whereDate($column, '<', $value) : null,
            'after' => $single ? $query->whereDate($column, '>', $value) : null,
            'between' => is_array($value) && array_is_list($value) && count($value) === 2
                && is_string($value[0]) && $value[0] !== '' && is_string($value[1]) && $value[1] !== ''
                ? $query->whereDate($column, '>=', $value[0])->whereDate($column, '<=', $value[1]) : null,
            'is_empty' => $query->whereNull($column),
            default => null,
        };
    }

    /**
     * Apply boolean filter operators.
     *
     * @param  Builder<TModel>  $query
     */
    protected function applyBooleanFilter(Builder $query, string $column, string $operator, mixed $value): void
    {
        $boolValue = filter_var($value, FILTER_VALIDATE_BOOLEAN);

        match ($operator) {
            'is_true' => $query->where($column, '=', true),
            'is_false' => $query->where($column, '=', false),
            '' => null, // "Any": no filter.
            default => $query->where($column, '=', $boolValue),
        };
    }

    /**
     * Apply number filter operators.
     *
     * @param  Builder<TModel>  $query
     */
    protected function applyNumberFilter(Builder $query, string $column, string $operator, mixed $value): void
    {
        if ($operator === 'between') {
            if (
                ! is_array($value)
                || ! array_is_list($value)
                || count($value) !== 2
                || ! is_numeric($value[0])
                || ! is_numeric($value[1])
            ) {
                return;
            }

            $query->where($column, '>=', $value[0])->where($column, '<=', $value[1]);

            return;
        }

        if ($value === null || $value === '') {
            return;
        }

        $numValue = is_numeric($value) ? $value : null;
        if ($numValue === null) {
            return;
        }

        match ($operator) {
            'equals' => $query->where($column, '=', $numValue),
            'not_equals' => $query->where($column, '!=', $numValue),
            'greater_than' => $query->where($column, '>', $numValue),
            'greater_than_or_equal' => $query->where($column, '>=', $numValue),
            'less_than' => $query->where($column, '<', $numValue),
            'less_than_or_equal' => $query->where($column, '<=', $numValue),
            default => null,
        };
    }

    /**
     * Apply select filter operators.
     *
     * @param  Builder<TModel>  $query
     */
    protected function applySelectFilter(Builder $query, string $column, string $operator, mixed $value, bool $isMultiSelect, bool $nullable): void
    {
        if ($operator === 'is_empty' && $nullable) {
            $query->whereNull($column);

            return;
        }

        if ($value === null || $value === '' || (is_array($value) && $value === [])) {
            return;
        }

        $values = is_array($value) ? $value : [$value];
        $values = array_filter($values, fn ($v) => $v !== null && $v !== '');

        if ($values === []) {
            return;
        }

        if ($isMultiSelect) {
            match ($operator) {
                'equals', 'in' => $query->whereIn($column, $values),
                'not_equals', 'not_in' => $query->whereNotIn($column, $values),
                default => $query->whereIn($column, $values),
            };
        } else {
            $singleValue = array_values($values)[0];
            match ($operator) {
                'equals' => $query->where($column, '=', $singleValue),
                'not_equals' => $query->where($column, '!=', $singleValue),
                default => $query->where($column, '=', $singleValue),
            };
        }
    }

    /**
     * Apply a select filter on a to-many relation, where `column` belongs to the related table:
     * "equals" keeps the records holding any of the selected values, "not_equals" those holding
     * none of them and "is_empty" those holding none at all.
     *
     * @param  Builder<TModel>  $query
     */
    protected function applyRelationFilter(Builder $query, string $relation, string $column, string $operator, mixed $value): void
    {
        if ($operator === 'is_empty') {
            $query->whereDoesntHave($relation);

            return;
        }

        $values = array_values(array_filter(is_array($value) ? $value : [$value], fn ($v) => $v !== null && $v !== ''));

        if ($values === []) {
            return;
        }

        $constraint = fn (Builder $related): Builder => $related->whereIn($column, $values);

        if ($operator === 'not_equals') {
            $query->whereDoesntHave($relation, $constraint);
        } else {
            $query->whereHas($relation, $constraint);
        }
    }

    /**
     * Escape LIKE wildcards and the escape character itself in a value.
     */
    protected function escapeLikeValue(string $value): string
    {
        return strtr($value, [
            '%' => self::LIKE_ESCAPE.'%',
            '_' => self::LIKE_ESCAPE.'_',
            self::LIKE_ESCAPE => self::LIKE_ESCAPE.self::LIKE_ESCAPE,
        ]);
    }

    /**
     * Number of records in each list state, shown as the badge of the state tabs.
     *
     * @return array{active: int, archived: int, trashed: int}
     */
    public function stateCounts(
        ?int $activeTotal = null,
        ?int $archivedTotal = null,
        ?int $trashedTotal = null,
    ): array {
        $modelClass = $this->getModelClass();

        return [
            'active' => $activeTotal ?? $modelClass::query()->where('active', true)->count(),
            'archived' => $archivedTotal ?? $modelClass::query()->where('active', false)->count(),
            'trashed' => $trashedTotal ?? self::countTrashed($modelClass),
        ];
    }

    /**
     * Count trashed records for the given model class.
     */
    private static function countTrashed(string $modelClass): int
    {
        return $modelClass::query()->withTrashed()->whereNotNull('deleted_at')->count();
    }

    /**
     * Find a soft-deleted record by name. A resource whose names are unique inside a parent
     * overrides this to scope the lookup by that parent.
     *
     * The record comes back as the query's own model, so a caller keeps its columns instead of
     * receiving the bare `Model` and losing them.
     *
     * @return TModel|null
     */
    public function findTrashedByName(string $name): ?Model
    {
        return $this->firstTrashedByName($this->trashedQuery(), $name);
    }

    /**
     * @return Builder<TModel>
     */
    protected function trashedQuery(): Builder
    {
        /** @var Builder<TModel> $query */
        /** @phpstan-ignore method.notFound */
        $query = $this->getModelClass()::query()->withTrashed()->whereNotNull('deleted_at');

        return $query;
    }

    /**
     * @param  Builder<TModel>  $query
     * @return TModel|null
     */
    protected function firstTrashedByName(Builder $query, string $name): ?Model
    {
        return $query->where('name', $name)->latest('deleted_at')->first();
    }

    /**
     * Base search columns for a list, with whatever the integration contributes appended.
     *
     * @param  non-empty-list<literal-string>  $columns
     * @return non-empty-list<literal-string>
     */
    protected function searchColumns(string $scope, array $columns): array
    {
        /** @var non-empty-list<literal-string> $columns An integration appends to the base columns; it never replaces them. */
        /** @phpstan-ignore method.notFound */
        $columns = $this->integration()->searchColumns($scope, $columns);

        return $columns;
    }

    /**
     * Get the default operators for a given field type.
     *
     * @param  string  $type  One of 'text', 'date', 'boolean', 'number', 'select'
     * @param  bool  $nullable  Whether the field is nullable (affects date/select)
     * @return list<string>
     */
    public static function getDefaultOperatorsForType(string $type, bool $nullable = false): array
    {
        return match ($type) {
            'text' => ['contains', 'equals', 'not_equals', 'starts_with', 'ends_with'],
            'date' => $nullable
                ? ['equals', 'before', 'after', 'between', 'is_empty']
                : ['equals', 'before', 'after', 'between'],
            'boolean' => ['is_true', 'is_false'],
            'number' => ['equals', 'not_equals', 'greater_than', 'greater_than_or_equal', 'less_than', 'less_than_or_equal', 'between'],
            'select' => $nullable
                ? ['equals', 'not_equals', 'is_empty']
                : ['equals', 'not_equals'],
            default => ['equals', 'not_equals'],
        };
    }

    /**
     * Get the default operator for a given field type.
     *
     * @param  string  $type  One of 'text', 'date', 'boolean', 'number', 'select'
     */
    public static function getDefaultOperatorForType(string $type): string
    {
        return match ($type) {
            'text' => 'contains',
            'date' => 'between',
            'boolean' => '',
            'number' => 'equals',
            'select' => 'equals',
            default => 'equals',
        };
    }

    /**
     * Get the searchable field definitions for the current scope.
     * Each field defines: column, label, type, operators, default_operator, visible, order, group, nullable, placeholder, help, condition, options, multiple.
     * The operators and default_operator will use type-aware defaults if not explicitly provided.
     *
     * @param  string  $scope  One of 'active', 'archived', 'trashed'
     * @return SearchFields
     */
    abstract public function searchableFields(string $scope): array;

    /**
     * The fully qualified model class name for this query.
     *
     * @return class-string<Model>
     */
    abstract protected function getModelClass(): string;

    /**
     * The integration instance for this query.
     */
    abstract protected function integration(): object;

    /**
     * Escape the LIKE wildcards and the escape character itself in a search term.
     */
    protected function searchPattern(string $search): string
    {
        return '%'.strtr($search, [
            '%' => self::LIKE_ESCAPE.'%',
            '_' => self::LIKE_ESCAPE.'_',
            self::LIKE_ESCAPE => self::LIKE_ESCAPE.self::LIKE_ESCAPE,
        ]).'%';
    }
}
