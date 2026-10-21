{{--
    Filter badges component for page header.
    Displays active filters as badges. Removing one, or all of them, is a plain link to the list URL
    without those filters, so the page reloads with the badges, the results and the modal in step.

    :filters   badges built by the page header: key, label, operator, value and url (without it).
    :clearUrl  list URL without any filter.
--}}
@props(['filters' => [], 'prefix' => '', 'clearUrl'])

<div class="flex flex-wrap items-center gap-2" data-test="{{ $prefix }}-filter-badges">
    @foreach ($filters as $filter)
        <flux:badge variant="outline" class="gap-1">
            <span class="font-medium">{{ $filter['label'] }}:</span>
            <span>{{ $filter['operator'] }}</span>
            @if ($filter['value'] !== '')
                <span>{{ $filter['value'] }}</span>
            @endif
            <flux:button :href="$filter['url']" variant="ghost" size="xs" square icon="x-mark"
                :data-test="$prefix.'-filter-remove-'.$filter['key']"
                :aria-label="__('basics13::messages.remove_filter') . ': ' . $filter['label']" />
        </flux:badge>
    @endforeach
    <flux:button :href="$clearUrl" variant="ghost" size="sm" :data-test="$prefix.'-filter-clear-all'"
        class="text-sm text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white">
        {{ __('basics13::messages.clear_all_filters') }}
    </flux:button>
</div>
