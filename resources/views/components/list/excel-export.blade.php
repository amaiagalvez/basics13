@props([
    'prefix',
    'route' => null,
    'scope' => null,
    'label' => null,
    'icon' => 'arrow-down-tray',
    'variant' => 'outline',
    'size' => 'sm',
    'enabled' => true,
])

@php
    $prefix = $prefix ?? 'list';
    $label = $label ?? __('basics13::messages.export_to_excel');
    $route = $route ?? request()->url();
    // The listing knows its scope; the query string only carries it when the route is the same.
    $scope ??= request()->query('scope');
@endphp

@if ($enabled)
    <form method="GET" action="{{ $route }}" class="contents">
        @if (request()->query('search'))
            <input type="hidden" name="search" value="{{ request()->query('search') }}">
        @endif
        @foreach ((array) request()->query('filters', []) as $key => $filter)
            @continue(! is_array($filter))
            @foreach ($filter as $filterKey => $filterValue)
                @if (is_array($filterValue))
                    @foreach ($filterValue as $valueIndex => $item)
                        <input type="hidden" name="filters[{{ $key }}][{{ $filterKey }}][{{ $valueIndex }}]" value="{{ $item }}">
                    @endforeach
                @else
                    <input type="hidden" name="filters[{{ $key }}][{{ $filterKey }}]" value="{{ $filterValue }}">
                @endif
            @endforeach
        @endforeach
        @if ($scope)
            <input type="hidden" name="scope" value="{{ $scope }}">
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
