@props([
    'prefix',
    'route' => null,
    'label' => null,
    'icon' => 'arrow-down-tray',
    'variant' => 'outline',
    'size' => 'sm',
    'fileName' => null,
    'columns' => [],
    'columnDefinitions' => [],
    'title' => 'Export',
    'enabled' => true,
])

@php
    $prefix = $prefix ?? 'list';
    $label = $label ?? __('basics13::messages.export_to_excel');
    $fileName = $fileName ?? $prefix.'_export_'.now()->format('Y-m-d_H-i-s').'.xlsx';
    $route = $route ?? request()->url();
@endphp

@if ($enabled)
    <form method="GET" action="{{ $route }}" class="contents">
        @if (request()->query('search'))
            <input type="hidden" name="search" value="{{ request()->query('search') }}">
        @endif
        @if (request()->query('filters'))
            @foreach (request()->query('filters') as $key => $filter)
                @if (is_array($filter))
                    @foreach ($filter as $filterKey => $filterValue)
                        <input type="hidden" name="filters[{{ $key }}][{{ $filterKey }}]" value="{{ is_array($filterValue) ? json_encode($filterValue) : $filterValue }}">
                    @endforeach
                @else
                    <input type="hidden" name="filters[{{ $key }}]" value="{{ is_array($filterValue) ? json_encode($filterValue) : $filterValue }}">
                @endif
            @endforeach
        @endif
        @if (request()->query('scope'))
            <input type="hidden" name="scope" value="{{ request()->query('scope') }}">
        @endif

        <flux:button
            type="submit"
            :variant="$variant"
            :size="$size"
            :icon="$icon"
            :data-test="$prefix.'-excel-export'"
            wire:loading.attr="disabled"
            class="whitespace-nowrap"
        >
            {{ $label }}
        </flux:button>
    </form>
@endif