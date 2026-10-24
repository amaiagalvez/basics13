{{-- The sidebar the packages seed: every entry shows only to whoever holds a permission its page asks for. --}}
@php
    $isActive = static fn (array $item): bool => $item['active_pattern'] !== null
        && request()->routeIs($item['active_pattern'])
        && ! ($item['active_except'] !== null && request()->routeIs($item['active_except']));
@endphp
@foreach (\Basics13\Support\NavigationMenu::for(auth()->user()) as $entry)
    @if ($entry['children'] === [])
        <flux:sidebar.item :icon="$entry['item']['icon']" :href="route($entry['item']['route'])"
            :current="$isActive($entry['item'])" wire:navigate data-test="nav-{{ $entry['item']['key'] }}">
            {{ __($entry['item']['label']) }}
        </flux:sidebar.item>
    @else
        <flux:sidebar.group :heading="__($entry['item']['label'])" class="grid" data-test="nav-group-{{ $entry['item']['key'] }}">
            @foreach ($entry['children'] as $item)
                <flux:sidebar.item :icon="$item['icon']" :href="route($item['route'])"
                    :current="$isActive($item)" wire:navigate data-test="nav-{{ $item['key'] }}">
                    {{ __($item['label']) }}
                </flux:sidebar.item>
            @endforeach
        </flux:sidebar.group>
    @endif
@endforeach
