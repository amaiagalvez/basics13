@props(['search', 'total' => null, 'scope' => 'active', 'filters' => []])

@php
    $resultsTemplate = __('Search results updated. Results: :count');
@endphp

<div data-list-results
    data-total-results="{{ $total ?? 0 }}"
    x-data="listSearch(@js($search['value']), @js($total ?? 0), @js($resultsTemplate))"
    x-on:input.debounce.400ms="searchInput($event)"
    x-on:submit="submitSearch($event)" x-on:click="handleClick($event)"
    class="flex flex-col gap-y-2">
    {{-- Visually hidden, so the new result count is announced when the table is replaced. The
         aria-live sits here rather than on the wrapper because the whole subtree is swapped on
         every search. listSearch fills it in after the morph. --}}
    <flux:text class="sr-only" role="status" aria-live="polite"
        data-list-results-announcement></flux:text>

    {{ $slot }}
</div>