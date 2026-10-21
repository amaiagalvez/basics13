{{--
    Filter badges component for page header.
    Displays active filters as badges with remove buttons.
--}}
@props(['filters' => [], 'prefix' => ''])

@php
    $filtersB64 = base64_encode(json_encode($filters, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE));
@endphp

<div class="flex flex-wrap gap-2"
    x-data="filterBadges()"
    data-filters-b64="{{ $filtersB64 }}"
    x-init="filters = JSON.parse(atob($el.dataset.filtersB64))">
    @foreach ($filters as $filter)
        <flux:badge variant="outline" class="gap-1">
            <span class="font-medium">{{ $filter['label'] }}:</span>
            <span>{{ $filter['operator'] }}</span>
            @if ($filter['value'] !== '')
                <span>{{ $filter['value'] }}</span>
            @endif
            <flux:button type="button" variant="ghost" size="xs" square icon="x-mark"
                wire:click="$dispatch('filter-removed', { key: '{{ $filter['key'] }}' })"
                :aria-label="{{ __('basics13::messages.remove_filter') . ': ' . $filter['label'] }}" />
        </flux:badge>
    @endforeach
    <flux:button type="button" variant="ghost" size="sm" wire:click="$dispatch('clear-all-filters')"
        class="text-sm text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white">
        {{ __('basics13::messages.clear_all_filters') }}
    </flux:button>
</div>

@verbatim
<script>
    function filterBadges() {
        return {
            filters: [],
            init() {
                this.filters = JSON.parse(atob(this.$el.dataset.filtersB64));
            },
            handleFilterRemoved(event) {
                this.filters = this.filters.filter(f => f.key !== event.detail.key);
                this.$dispatch('update-url', {
                    filters: this.filters.reduce((acc, f) => {
                        acc[f.key] = { operator: f.operator, value: f.value };
                        return acc;
                    }, {})
                });
            }
        };
    }
</script>
@endverbatim