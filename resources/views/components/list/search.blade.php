@props(['search', 'prefix'])

@php
    // The active advanced filters ride along with every search, so a search never drops them: as
    // hidden inputs for the native submit and in the clear link.
    $filters = request()->query('filters');
    $filtersQuery = is_array($filters) && $filters !== [] ? \Illuminate\Support\Arr::query(['filters' => $filters]) : '';
    $clearUrl = $search['action'].($filtersQuery === '' ? '' : '?'.$filtersQuery);
@endphp

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
