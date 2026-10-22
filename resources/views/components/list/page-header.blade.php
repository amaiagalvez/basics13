{{--
    Page heading for resource lists: breadcrumbs, H1 with its one-line description, the primary
    action and the state tabs. Rendered outside the "list-results" fragment so it stays stable while
    the results morph on search.

    The breadcrumbs only show when there is a hierarchy to walk back (archived, trash): on the
    active list they would just repeat the H1.
--}}
@props(['list', 'prefix', 'scope' => 'active', 'searchableFields' => [], 'filters' => [], 'description' => null])

@php
    // Determine if advanced search button should be shown
    $showAdvancedSearch = false;
    if (!empty($searchableFields)) {
        foreach ($searchableFields as $field) {
            $visible = $field['visible'] ?? true;
            if (isset($field['condition']) && is_callable($field['condition'])) {
                $visible = $visible && $field['condition']($scope);
            }
            if ($visible) {
                $showAdvancedSearch = true;
                break;
            }
        }
    }

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

        $operatorKey = 'basics13::messages.operator_'.$operator;
        $operatorLabel = __($operatorKey) !== $operatorKey ? __($operatorKey) : ucfirst(str_replace('_', ' ', $operator));
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

<div {{ $attributes->class('flex flex-col gap-4') }}>
    <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-3">
        <div class="flex min-w-0 flex-col gap-1">
            @if (count($list['breadcrumbs']) > 1)
                <flux:breadcrumbs class="text-sm text-zinc-600 dark:text-zinc-400" :data-test="$prefix.'-breadcrumbs'">
                    @foreach ($list['breadcrumbs'] as $breadcrumb)
                        @if ($loop->last)
                            {{-- The current page links to its own URL, search included. --}}
                            <flux:breadcrumbs.item :href="request()->fullUrl()" aria-current="page"
                                class="transition-colors hover:text-zinc-800 dark:hover:text-zinc-200">
                                {{ $breadcrumb['label'] }}
                            </flux:breadcrumbs.item>
                        @else
                            <flux:breadcrumbs.item :href="$breadcrumb['url']" wire:navigate
                                class="transition-colors hover:text-zinc-800 dark:hover:text-zinc-200">
                                {{ $breadcrumb['label'] }}
                            </flux:breadcrumbs.item>
                        @endif
                    @endforeach
                </flux:breadcrumbs>
            @endif

            <flux:heading level="1" size="xl" class="font-display text-3xl! font-semibold! tracking-tight"
                :data-test="$prefix.'-heading'">
                {{ $list['resource'] }}
            </flux:heading>

            @if ($description)
                <flux:text class="text-zinc-600 dark:text-zinc-400" :data-test="$prefix.'-description'">
                    {{ $description }}
                </flux:text>
            @endif
        </div>

        @if (isset($actions) && $actions->isNotEmpty())
            <div class="flex shrink-0 items-center gap-2">
                {{ $actions }}
            </div>
        @endif
    </div>

    {{-- Active filter badges --}}
    @if (!empty($activeFilters))
        <x-basics13::list.filter-badges :filters="$activeFilters" :prefix="$prefix" :clear-url="$listUrlWithout()" />
    @endif

    <x-basics13::list.tabs :tabs="$list['tabs']" :prefix="$prefix">
        @if ($showAdvancedSearch)
            <x-slot:actions>
                <flux:modal.trigger name="advanced-search">
                    <flux:button variant="outline" size="sm" icon="funnel"
                        :data-test="$prefix.'-advanced-search'">
                        {{ __('basics13::messages.advanced_search') }}
                    </flux:button>
                </flux:modal.trigger>
            </x-slot:actions>
        @endif
    </x-basics13::list.tabs>
</div>