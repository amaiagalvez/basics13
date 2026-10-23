<?php

namespace Basics13\Livewire;

use Livewire\Component;
use Livewire\Attributes\Locked;
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
    /**
     * Resolved once in mount: no callable survives into the snapshot, and the client cannot change
     * the definitions, so nothing taken from component state is ever invoked.
     *
     * @var SearchFields
     */
    #[Locked]
    public array $fields = [];

    /** @var SearchFilters */
    public array $filters = [];

    #[Locked]
    public string $scope = 'active';

    public bool $show = false;

    public bool $showAdvanced = false;

    /**
     * @param  SearchFields  $fields
     * @param  SearchFilters  $filters
     */
    public function mount(
        array $fields = [],
        array $filters = [],
        string $scope = 'active',
    ): void {
        /** @var SearchFields $processedFields */
        $processedFields = [];
        foreach ($fields as $key => $field) {
            // A field whose condition fails does not apply to this list at all, not even as an
            // advanced one.
            if (isset($field['condition']) && ! $field['condition']($scope)) {
                continue;
            }

            $processedFields[$key] = $this->normalizeField($field, $scope);
        }

        $this->fields = $processedFields;
        $this->filters = $filters;
        $this->scope = $scope;

        // Initialize filter fields with default operators. A multi-select starts as an empty list:
        // Livewire binds its checkboxes to an array only when the property already holds one.
        foreach ($this->fields as $fieldKey => $field) {
            if (! isset($this->filters[$fieldKey])) {
                $this->filters[$fieldKey] = [
                    'operator' => $field['default_operator'] ?? $field['operators'][0] ?? 'contains',
                    'value' => $this->fieldIsMultiSelect($field) ? [] : null,
                ];
            }
        }
    }

    /**
     * Get fields filtered by visibility, grouped and ordered.
     *
     * @return SearchFields
     */
    public function getVisibleFields(): array
    {
        $visible = [];

        foreach ($this->fields as $key => $field) {
            if (($field['visible'] ?? true) || $this->showAdvanced) {
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
            $labels[$operator] = $translation !== $translationKey
                ? $translation
                : ucfirst(str_replace('_', ' ', $operator));
        }

        return $labels;
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
     * @return array<array-key, string>
     */
    public function getSelectOptions(array $field): array
    {
        $options = $field['options'] ?? [];

        if (! is_array($options)) {
            return [];
        }

        /** @var array<array-key, string> $options Mount already resolved any callable into its list. */
        return $options;
    }

    /**
     * A date operator decides between one input and a from/to pair, so its value starts over when
     * the operator changes: a pair left behind would reach a single-date operator and the other way.
     */
    public function updatedFilters(mixed $value, ?string $key = null): void
    {
        if ($key === null) {
            return;
        }

        [$fieldKey, $property] = array_pad(explode('.', $key, 2), 2, null);

        if ($property === 'operator' && ($this->fields[$fieldKey]['type'] ?? null) === 'date') {
            $this->filters[$fieldKey]['value'] = null;
        }
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

            // A boolean without operator is "any": nothing to filter.
            if ($isBoolean && $operator === '') {
                continue;
            }

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
            if (($field['type'] ?? '') === 'boolean') {
                $filter['operator'] = $field['default_operator'] ?? '';
            }

            $filter['value'] = match (true) {
                ($field['type'] ?? '') === 'boolean' => false,
                $field !== null && $this->fieldIsMultiSelect($field) => [],
                default => null,
            };
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
     * Normalize a field definition with defaults, resolving its options for the scope.
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
        unset($field['condition']);

        if (isset($field['options']) && is_callable($field['options'])) {
            $field['options'] = $field['options']($scope);
        }

        return $field;
    }
}
