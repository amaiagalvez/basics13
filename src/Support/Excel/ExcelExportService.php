<?php

declare(strict_types=1);

namespace Basics13\Support\Excel;

use Illuminate\Support\Str;
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
 * Downloads a listing to Excel.
 *
 * The query arrives already filtered by the same list query the page uses, so the file holds
 * exactly the rows the user is looking at: search, advanced filters and scope included.
 *
 * @template TModel of Model
 */
final class ExcelExportService implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle
{
    use Exportable;

    /**
     * @param  Builder<TModel>  $query
     * @param  array<int, string>  $columns
     * @param  array<int, array{key: string, label: string, transformer?: callable(TModel): mixed}>  $columnDefinitions
     */
    public function __construct(
        private readonly Builder $query,
        private readonly string $title = 'Export',
        private readonly array $columns = [],
        private readonly array $columnDefinitions = [],
        private readonly int $maxRows = 10000,
    ) {}

    public function download(string $fileName = 'export.xlsx'): BinaryFileResponse
    {
        return Excel::download($this, $fileName, ExcelFormat::XLSX);
    }

    /**
     * Name the downloaded file in the user's language: the translated resource, the scope when the
     * listing is the archived or the trash one, and a timestamp.
     */
    public static function fileName(string $label, string $scope = 'active'): string
    {
        $parts = [Str::slug($label)];

        $scopeKey = match ($scope) {
            'archived' => 'archived_label',
            'trashed' => 'trash_label',
            default => null,
        };

        if ($scopeKey !== null) {
            $parts[] = Str::slug(__('basics13::messages.'.$scopeKey));
        }

        $parts[] = now()->format('Y-m-d_H-i-s');

        return implode('_', $parts).'.xlsx';
    }

    public function store(string $fileName = 'export.xlsx', string $disk = 'local'): bool
    {
        return Excel::store($this, $fileName, $disk, ExcelFormat::XLSX);
    }

    // FromCollection implementation

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function collection(): Collection
    {
        $query = clone $this->query;

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
