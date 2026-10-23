{{--
    Toolbar of a resource list: the search on the left and, when the scope has visible filterable
    fields, the advanced search on the right, with the active filters as badges below. Everything
    that narrows the rows sits together, right above them.
--}}
@props(['search', 'prefix', 'scope' => 'active', 'searchableFields' => [], 'filters' => []])

@php
    // The active advanced filters ride along with every search, so a search never drops them: as
    // hidden inputs for the native submit and in the clear link.
    $queryFilters = request()->query('filters');
    $filtersQuery = is_array($queryFilters) && $queryFilters !== [] ? \Illuminate\Support\Arr::query(['filters' => $queryFilters]) : '';
    $clearUrl = $search['action'].($filtersQuery === '' ? '' : '?'.$filtersQuery);

    $isVisible = function (array $field) use ($scope): bool {
        $visible = $field['visible'] ?? true;
        if (isset($field['condition']) && is_callable($field['condition'])) {
            $visible = $visible && $field['condition']($scope);
        }

        return $visible;
    };
    $showAdvancedSearch = collect($searchableFields)->contains($isVisible);

    // Build active filter badges. Each one links to the list without its filter, back to page one.
    $listUrlWithout = function (?string $fieldKey = null): string {
        $query = request()->except(['page', 'filters']);
        if ($fieldKey !== null) {
            $remaining = \Illuminate\Support\Arr::except((array) request()->query('filters', []), $fieldKey);
            if ($remaining !== []) {
                $query['filters'] = $remaining;
            }
        }

        return request()->url().($query === [] ? '' : '?'.\Illuminate\Support\Arr::query($query));
    };
    $activeFilters = [];
    foreach ($filters as $fieldKey => $filter) {
        $field = $searchableFields[$fieldKey] ?? null;
        if (!$field) continue;

        $operator = $filter['operator'] ?? '';
        $value = $filter['value'] ?? null;

        $isBoolean = ($field['type'] ?? '') === 'boolean';

        // A boolean filter only applies with one of its operators; the empty one means "any".
        if ($isBoolean && ! in_array($operator, ['is_true', 'is_false'], true)) {
            continue;
        }

        if (! $isBoolean && ($value === '' || $value === [] || $value === null) && $operator !== 'is_empty') {
            continue;
        }

        // The field's default operator goes without saying ("Name: 001" rather than "Name: contains
        // 001"); any other one is spelled out.
        $defaultOperator = $field['default_operator'] ?? \Basics13\Queries\ListQueryBase::getDefaultOperatorForType($field['type'] ?? 'text');
        $operatorKey = 'basics13::messages.operator_'.$operator;
        $operatorLabel = $isBoolean || $operator !== $defaultOperator
            ? (__($operatorKey) !== $operatorKey ? __($operatorKey) : ucfirst(str_replace('_', ' ', $operator)))
            : '';
        $fieldLabel = __($field['label']);

        $valueLabel = '';
        if ($isBoolean) {
            // The operator already reads "Yes" or "No".
        } elseif (is_array($value)) {
            $options = ($field['type'] ?? '') === 'select' && isset($field['options'])
                ? (is_callable($field['options']) ? $field['options']($scope) : $field['options'])
                : [];
            $valueLabel = implode(', ', array_map(fn ($v) => $options[$v] ?? $v, $value));
        } elseif ($value !== null && $value !== '') {
            $valueLabel = (string) $value;
        } elseif ($operator === 'is_empty') {
            // "Is empty" already reads as the value.
            $operatorLabel = '';
            $valueLabel = __('basics13::messages.is_empty');
        }

        $activeFilters[] = [
            'key' => $fieldKey,
            'label' => $fieldLabel,
            'operator' => $operatorLabel,
            'value' => $valueLabel,
            'url' => $listUrlWithout($fieldKey),
        ];
    }
@endphp

<div class="flex flex-col gap-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <form method="GET" action="{{ $search['action'] }}"
            data-list-search
            class="flex w-full items-center gap-2 sm:max-w-md">
            @if ($filtersQuery !== '')
                @foreach (explode('&', $filtersQuery) as $pair)
                    @php [$name, $value] = array_map('urldecode', array_pad(explode('=', $pair, 2), 2, '')); @endphp
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
            @endif
            <div class="relative min-w-0 flex-1">
                <flux:input x-ref="search" class="min-w-0 flex-1" name="search"
                    :aria-label="__('Search')" :placeholder="$search['placeholder']"
                    :value="$search['value']" maxlength="{{ \Basics13\Support\Validation\MaxLength::string() }}"
                    icon="magnifying-glass" aria-keyshortcuts="/"
                    class:input="pe-10" :data-test="$prefix.'-search'" />
                @if ($search['value'] !== '')
                    <div class="absolute inset-e-1 top-1/2 z-10 -translate-y-1/2">
                        <flux:button :href="$clearUrl" variant="ghost" size="sm" square
                            icon="x-mark" title="{{ __('Clear') }}" :aria-label="__('Clear')"
                            data-list-search-clear :data-test="$prefix.'-search-clear'" />
                    </div>
                @endif
            </div>
        </form>

        @if ($showAdvancedSearch)
            <flux:modal.trigger name="advanced-search">
                <flux:button variant="outline" icon="funnel" :data-test="$prefix.'-advanced-search'">
                    {{ __('basics13::messages.advanced_search') }}
                    @if ($activeFilters !== [])
                        <span class="ms-1 rounded-full bg-brand-700 px-1.5 py-0.5 text-xs font-medium tabular-nums text-white dark:bg-brand-400 dark:text-zinc-950"
                            data-test="{{ $prefix }}-advanced-search-count">{{ count($activeFilters) }}</span>
                    @endif
                </flux:button>
            </flux:modal.trigger>
        @endif
    </div>

    @if ($activeFilters !== [])
        <x-basics13::list.filter-badges :filters="$activeFilters" :prefix="$prefix" :clear-url="$listUrlWithout()" />
    @endif
</div>
