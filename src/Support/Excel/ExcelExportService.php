<?php

declare(strict_types=1);

namespace Basics13\Support\Excel;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * @template TModel of Model
 */
final class ExcelExportService implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle
{
    use Exportable;

    /**
     * @param  Builder<TModel>  $query
     * @param  array<string, array<string, mixed>>  $filters
     * @param  array<int, string>  $columns
     * @param  array<int, array{key: string, label: string, transformer?: callable(TModel): mixed}>  $columnDefinitions
     */
    public function __construct(
        private readonly Builder $query,
        private readonly array $filters = [],
        private readonly string $title = 'Export',
        private readonly array $columns = [],
        private readonly array $columnDefinitions = [],
        private readonly int $maxRows = 10000,
    ) {}

    /**
     * Create a new export service from the current request.
     *
     * @template TExportModel of Model
     *
     * @param  Builder<TExportModel>  $query
     * @param  array<int, string>  $columns
     * @param  array<int, array{key: string, label: string, transformer?: callable(TExportModel): mixed}>  $columnDefinitions
     * @return self<TExportModel>
     */
    public static function fromRequest(
        Request $request,
        Builder $query,
        array $columns,
        array $columnDefinitions = [],
        string $title = 'Export',
        int $maxRows = 10000,
    ): self {
        /** @var array<string, array<string, mixed>> $filters */
        $filters = $request->array('filters');

        return new self($query, $filters, $title, $columns, $columnDefinitions, $maxRows);
    }

    public function download(string $fileName = 'export.xlsx'): BinaryFileResponse
    {
        return Excel::download($this, $fileName, ExcelFormat::XLSX);
    }

    public function store(string $fileName = 'export.xlsx', string $disk = 'local'): bool
    {
        return Excel::store($this, $fileName, $disk, ExcelFormat::XLSX);
    }

    /**
     * Get the query with filters applied.
     *
     * @return Builder<TModel>
     */
    public function getFilteredQuery(): Builder
    {
        $query = clone $this->query;

        $this->applySearchFilter($query);
        $this->applyAdvancedFilters($query);
        $this->applyScopeFilter($query);

        return $query;
    }

    /**
     * Apply the simple search filter.
     *
     * @param  Builder<TModel>  $query
     */
    protected function applySearchFilter(Builder $query): void
    {
        $search = request()->string('search')->toString();

        if ($search === '') {
            return;
        }

        $model = $this->query->getModel();
        $searchableFields = method_exists($model, 'getSearchableFields')
            ? ($model->getSearchableFields(request()->query('scope', 'active')) ?? [])
            : [];

        if (empty($searchableFields)) {
            return;
        }

        $query->where(function (Builder $q) use ($search, $searchableFields): void {
            $escapedSearch = str_replace(['%', '_'], ['\%', '\_'], $search);

            foreach ($searchableFields as $field) {
                $q->orWhere($field, 'LIKE', "%{$escapedSearch}%");
            }
        });
    }

    /**
     * Apply advanced filters from the request.
     *
     * @param  Builder<TModel>  $query
     */
    protected function applyAdvancedFilters(Builder $query): void
    {
        foreach ($this->filters as $fieldKey => $filter) {
            $this->applyFilter($query, $fieldKey, $filter);
        }
    }

    /**
     * Apply a single filter to the query.
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $filter
     */
    protected function applyFilter(Builder $query, string $fieldKey, array $filter): void
    {
        $operator = $filter['operator'] ?? '';
        $value = $filter['value'] ?? null;

        if ($value === '' || $value === [] || $value === null) {
            if ($operator !== 'is_empty') {
                return;
            }
        }

        $column = $this->resolveColumn($fieldKey);

        match ($operator) {
            'equals' => $this->applyEqualsFilter($query, $column, $value),
            'not_equals' => $this->applyNotEqualsFilter($query, $column, $value),
            'contains' => $this->applyContainsFilter($query, $column, $value),
            'not_contains' => $this->applyNotContainsFilter($query, $column, $value),
            'starts_with' => $query->where($column, 'LIKE', $this->escapeWildcards($value).'%'),
            'ends_with' => $query->where($column, 'LIKE', '%'.$this->escapeWildcards($value)),
            'is_empty' => $query->where(function (Builder $q) use ($column): void {
                $q->whereNull($column)->orWhere($column, '');
            }),
            'is_not_empty' => $query->where(function (Builder $q) use ($column): void {
                $q->whereNotNull($column)->where($column, '!=', '');
            }),
            'is_true' => $query->where($column, true),
            'is_false' => $query->where($column, false),
            'gt' => $query->where($column, '>', $value),
            'gte' => $query->where($column, '>=', $value),
            'lt' => $query->where($column, '<', $value),
            'lte' => $query->where($column, '<=', $value),
            'between' => $query->whereBetween($column, (array) $value),
            default => null,
        };
    }

    /**
     * Apply equals filter (handles multiple select).
     *
     * @param  Builder<TModel>  $query
     */
    protected function applyEqualsFilter(Builder $query, string $column, mixed $value): void
    {
        if (is_array($value)) {
            $query->whereIn($column, $value);
        } else {
            $query->where($column, $value);
        }
    }

    /**
     * Apply not equals filter (handles multiple select).
     *
     * @param  Builder<TModel>  $query
     */
    protected function applyNotEqualsFilter(Builder $query, string $column, mixed $value): void
    {
        if (is_array($value)) {
            $query->whereNotIn($column, $value);
        } else {
            $query->where($column, '!=', $value);
        }
    }

    /**
     * Apply contains filter with wildcard escaping.
     *
     * @param  Builder<TModel>  $query
     */
    protected function applyContainsFilter(Builder $query, string $column, string $value): void
    {
        $escaped = $this->escapeWildcards($value);
        $query->where($column, 'LIKE', "%{$escaped}%");
    }

    /**
     * Apply not contains filter with wildcard escaping.
     *
     * @param  Builder<TModel>  $query
     */
    protected function applyNotContainsFilter(Builder $query, string $column, string $value): void
    {
        $escaped = $this->escapeWildcards($value);
        $query->where($column, 'NOT LIKE', "%{$escaped}%");
    }

    /**
     * Apply scope filter (active/trash/archived).
     *
     * @param  Builder<TModel>  $query
     */
    protected function applyScopeFilter(Builder $query): void
    {
        $scope = request()->query('scope', 'active');

        match ($scope) {
            // The exported model is soft deletable, a scope PHPStan cannot see through the shared
            // `Model` base.
            /** @phpstan-ignore method.notFound */
            'trashed' => $query->onlyTrashed(),
            'archived' => $query->where('active', false),
            default => $query->where('active', true),
        };
    }

    /**
     * Resolve the actual database column for a filter field key.
     */
    protected function resolveColumn(string $fieldKey): string
    {
        $model = $this->query->getModel();

        // Check if the model has a filterColumns mapping
        if (method_exists($model, 'getFilterColumns')) {
            $columns = $model->getFilterColumns();

            return $columns[$fieldKey] ?? $fieldKey;
        }

        return $fieldKey;
    }

    /**
     * Escape wildcards for LIKE queries.
     */
    protected function escapeWildcards(string $value): string
    {
        return str_replace(['%', '_'], ['\%', '\_'], $value);
    }

    // FromCollection implementation

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function collection(): Collection
    {
        $query = $this->getFilteredQuery();

        // Limit the number of rows for performance
        if ($this->maxRows > 0) {
            $query->limit($this->maxRows);
        }

        $records = $query->get();

        if ($this->columnDefinitions !== []) {
            /** @var Collection<int, array<string, mixed>> $rows */
            $rows = $records->map(function (Model $record): array {
                $row = [];

                foreach ($this->columnDefinitions as $colDef) {
                    $key = $colDef['key'];
                    $transformer = $colDef['transformer'] ?? null;

                    if ($transformer !== null) {
                        $row[$key] = $transformer($record);
                    } elseif (isset($record->{$key})) {
                        $row[$key] = $record->{$key};
                    } elseif (method_exists($record, $key)) {
                        $row[$key] = $record->{$key}();
                    } else {
                        $row[$key] = '';
                    }
                }

                return $row;
            });

            return $rows;
        }

        return $records->map(function (Model $record): array {
            /** @var array<string, mixed> $attributes */
            $attributes = $record->toArray();

            return $this->columns === []
                ? $attributes
                : array_intersect_key($attributes, array_flip($this->columns));
        });
    }

    // WithHeadings implementation

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        if ($this->columnDefinitions !== []) {
            return array_map(fn ($colDef) => $colDef['label'], $this->columnDefinitions);
        }

        return $this->columns === [] ? [] : $this->columns;
    }

    // ShouldAutoSize implementation
    public function shouldAutoSize(): bool
    {
        return true;
    }

    // WithTitle implementation
    public function title(): string
    {
        return $this->title;
    }
}
