<?php

namespace Basics13\Livewire;

use Livewire\Component;
use Basics13\Queries\ListQueryBase;
use Illuminate\Contracts\View\View;

/**
 * Advanced Search Modal Component.
 *
 * Receives searchable field configuration and renders a modal with filter fields
 * grouped by type. Emits 'filters-applied' event with the selected filters.
 *
 * @phpstan-import-type SearchField from \Basics13\Queries\ListQueryBase
 * @phpstan-import-type SearchFields from \Basics13\Queries\ListQueryBase
 * @phpstan-import-type SearchFilters from \Basics13\Queries\ListQueryBase
 */
class AdvancedSearchModal extends Component
{
    /** @var SearchFields */
    public array $fields = [];

    /** @var SearchFilters */
    public array $filters = [];

    public string $scope = 'active';

    public bool $show = false;

    public bool $showAdvanced = false;

    /** @var array<string, string> */
    protected array $operatorLabels = [];

    /** @var array<string, string> */
    protected array $fieldTypeLabels = [];

    /** @var array<string, string> */
    protected array $groupLabels = [];

    /**
     * @param  SearchFields  $fields
     * @param  SearchFilters  $filters
     * @param  array<string, string>  $operatorLabels
     * @param  array<string, string>  $fieldTypeLabels
     * @param  array<string, string>  $groupLabels
     */
    public function mount(
        array $fields = [],
        array $filters = [],
        string $scope = 'active',
        array $operatorLabels = [],
        array $fieldTypeLabels = [],
        array $groupLabels = [],
    ): void {
        /** @var SearchFields $processedFields */
        $processedFields = [];
        foreach ($fields as $key => $field) {
            $field = $this->normalizeField($field, $scope);
            $processedFields[$key] = $field;
        }

        $this->fields = $processedFields;
        $this->filters = $filters;
        $this->scope = $scope;
        $this->operatorLabels = $operatorLabels;
        $this->fieldTypeLabels = $fieldTypeLabels;
        $this->groupLabels = $groupLabels;

        // Initialize filter fields with default operators
        foreach ($this->getVisibleFields() as $fieldKey => $field) {
            if (! isset($this->filters[$fieldKey])) {
                $this->filters[$fieldKey] = [
                    'operator' => $field['default_operator'] ?? $field['operators'][0] ?? 'contains',
                    'value' => null,
                ];
            }
        }
    }

    /**
     * Get fields filtered by visibility and condition, grouped and ordered.
     *
     * @return SearchFields
     */
    public function getVisibleFields(): array
    {
        $visible = [];

        foreach ($this->fields as $key => $field) {
            $visibleFlag = $field['visible'] ?? true;

            // Evaluate condition callable if present
            if (isset($field['condition'])) {
                $visibleFlag = $visibleFlag && $field['condition']($this->scope);
            }

            if ($visibleFlag || $this->showAdvanced) {
                $visible[$key] = $field;
            }
        }

        // Sort by group, then by order
        uasort($visible, function (array $a, array $b): int {
            $groupA = $a['group'] ?? 'general';
            $groupB = $b['group'] ?? 'general';
            $groupOrder = ['basic' => 1, 'names' => 2, 'dates' => 3, 'status' => 4, 'relations' => 5, 'general' => 6];

            $orderA = $groupOrder[$groupA] ?? 99;
            $orderB = $groupOrder[$groupB] ?? 99;

            if ($orderA !== $orderB) {
                return $orderA <=> $orderB;
            }

            return ($a['order'] ?? 999) <=> ($b['order'] ?? 999);
        });

        return $visible;
    }

    /**
     * Get fields grouped by group key.
     *
     * @return array<string, SearchFields>
     */
    public function getGroupedFields(): array
    {
        $grouped = [];

        foreach ($this->getVisibleFields() as $key => $field) {
            $group = $field['group'] ?? 'general';
            $grouped[$group][$key] = $field;
        }

        return $grouped;
    }

    /**
     * Get operators for a field type.
     *
     * @param  SearchField  $field
     * @return array<string, string>
     */
    public function getOperatorsForField(array $field): array
    {
        $operators = $field['operators'] ?? [];
        $labels = [];

        foreach ($operators as $operator) {
            $translationKey = 'basics13::messages.operator_'.$operator;
            $translation = __($translationKey);
            $labels[$operator] = $this->operatorLabels[$operator]
                ?? ($translation !== $translationKey ? $translation : ucfirst(str_replace('_', ' ', $operator)));
        }

        return $labels;
    }

    /**
     * Get the label for a group.
     */
    public function getGroupLabel(string $group): string
    {
        return $this->groupLabels[$group] ?? ucfirst($group);
    }

    /**
     * Check if a field should show the "is_empty" operator.
     *
     * @param  SearchField  $field
     */
    public function fieldAllowsIsEmpty(array $field): bool
    {
        return ($field['nullable'] ?? false) && in_array('is_empty', $field['operators'] ?? [], true);
    }

    /**
     * Check if a field is a select type with multiple values.
     *
     * @param  SearchField  $field
     */
    public function fieldIsMultiSelect(array $field): bool
    {
        return $field['type'] === 'select' && ($field['multiple'] ?? true);
    }

    /**
     * Get options for a select field.
     *
     * @param  SearchField  $field
     * @return array<int, string>
     */
    public function getSelectOptions(array $field): array
    {
        if (isset($field['options']) && is_callable($field['options'])) {
            return $field['options']($this->scope);
        }

        return $field['options'] ?? [];
    }

    /**
     * Apply filters and emit event.
     */
    public function applyFilters(): void
    {
        // Filter out empty values (except for boolean and is_empty operator)
        $activeFilters = [];

        foreach ($this->filters as $key => $filter) {
            $field = $this->fields[$key] ?? null;
            if (! $field) {
                continue;
            }

            $value = $filter['value'];
            $operator = $filter['operator'];

            // Skip if value is empty and not a boolean/is_empty operator
            $isEmpty = $value === '' || $value === [] || $value === null;
            $isBoolean = $field['type'] === 'boolean';
            $isIsEmptyOperator = $operator === 'is_empty';

            if ($isEmpty && ! $isBoolean && ! $isIsEmptyOperator) {
                continue;
            }

            // For select multiple, ensure value is array
            if ($this->fieldIsMultiSelect($field) && ! is_array($value)) {
                $value = $value !== null ? [$value] : [];
            }

            $activeFilters[$key] = [
                'operator' => $operator,
                'value' => $value,
            ];
        }

        $this->dispatch('filters-applied', filters: $activeFilters);
        $this->dispatch('modal-close', name: 'advanced-search');
    }

    /**
     * Clear all filters.
     */
    public function clearFilters(): void
    {
        foreach ($this->filters as $key => &$filter) {
            $field = $this->fields[$key] ?? null;
            $filter['value'] = ($field['type'] ?? '') === 'boolean' ? false : null;
        }

        $this->applyFilters();
    }

    /**
     * Toggle advanced fields visibility.
     */
    public function toggleAdvanced(): void
    {
        $this->showAdvanced = ! $this->showAdvanced;
    }

    public function render(): View
    {
        return view('basics13::components.list.advanced-search-modal');
    }

    /**
     * Normalize a field definition with defaults and condition evaluation.
     *
     * @param  SearchField  $field
     * @return SearchField
     */
    private function normalizeField(array $field, string $scope): array
    {
        $field['operators'] ??= ListQueryBase::getDefaultOperatorsForType(
            $field['type'],
            $field['nullable'] ?? false,
        );
        $field['default_operator'] ??= ListQueryBase::getDefaultOperatorForType($field['type']);

        if (isset($field['condition'])) {
            $field['visible'] = ($field['visible'] ?? true) && $field['condition']($scope);
            unset($field['condition']);
        }

        if (isset($field['options']) && is_callable($field['options'])) {
            $field['options'] = $field['options']($scope);
        }

        return $field;
    }
}
