{{--
    Page heading for resource lists: breadcrumbs, H1 and the state tabs. Rendered outside the
    "list-results" fragment so it stays stable while the results morph on search.

    The breadcrumbs already name the page, so the H1 is only there for the document outline and for
    screen readers: it is announced once, not repeated on screen.
--}}
@props(['list', 'prefix', 'scope' => 'active', 'searchableFields' => [], 'filters' => []])

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

        $operatorLabel = __('basics13::messages.operator_'.$operator) ?? ucfirst(str_replace('_', ' ', $operator));
        $fieldLabel = __($field['label']) ?? $field['label'] ?? $fieldKey;

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

    // Prepare base64 encoded JSON for Alpine.js
    $filtersB64 = base64_encode(json_encode($activeFilters, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE));
@endphp

<div {{ $attributes->class('flex flex-col gap-4') }}>
    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3">
        <div
            class="-mx-6 -mt-6 mb-2 w-[calc(100%+3rem)] border-b border-zinc-200 bg-zinc-50 pb-5 dark:border-zinc-700 dark:bg-zinc-900 lg:-mx-8 lg:-mt-8 lg:w-[calc(100%+4rem)]">
            <flux:breadcrumbs class="translate-y-2 ps-3 text-sm text-zinc-500 dark:text-zinc-400"
                :data-test="$prefix.
                '-breadcrumbs'">
                @foreach ($list['breadcrumbs'] as $breadcrumb)
                    @if ($loop->last)
                        {{-- The current page links to its own URL, search included. --}}
                        <flux:breadcrumbs.item :href="request()->fullUrl()" aria-current="page"
                            class="font-semibold text-zinc-900 dark:text-white">
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
        </div>

        <flux:heading level="1" size="xl" class="sr-only"
            :data-test="$prefix.
            '-heading'">
            {{ collect($list['breadcrumbs'])->pluck('label')->implode(' / ') }}
        </flux:heading>
    </div>

    {{-- Active filter badges --}}
    @if (!empty($activeFilters))
        <x-basics13::list.filter-badges :filters="$activeFilters" :prefix="$prefix" :clear-url="$listUrlWithout()" />
    @endif

    <x-basics13::list.tabs :tabs="$list['tabs']" :prefix="$prefix">
        @if (isset($actions) || $showAdvancedSearch)
            <x-slot:actions>
                <div class="flex items-center gap-2">
                    @isset($actions)
                        {{ $actions }}
                    @endisset

                    @if ($showAdvancedSearch)
                        <flux:modal.trigger name="advanced-search">
                            <flux:button variant="outline" size="sm" icon="funnel"
                                :data-test="$prefix.'-advanced-search'">
                                {{ __('basics13::messages.advanced_search') }}
                            </flux:button>
                        </flux:modal.trigger>
                    @endif
                </div>
            </x-slot:actions>
        @endif
    </x-basics13::list.tabs>
</div>