{{-- The sidebar the packages seed: every entry shows only to whoever holds a permission its page asks for. --}}
@php
    $isActive = static fn (array $item): bool => $item['active_pattern'] !== null
        && request()->routeIs($item['active_pattern'])
        && ! ($item['active_except'] !== null && request()->routeIs($item['active_except']));
    $containsCurrent = static function (array $entry) use (&$containsCurrent, $isActive): bool {
        if ($entry['children'] === []) {
            return $isActive($entry['item']);
        }

        foreach ($entry['children'] as $child) {
            if ($containsCurrent($child)) {
                return true;
            }
        }

        return false;
    };
@endphp
<div class="contents" data-sidebar-menu>
    @foreach (\Basics13\Support\NavigationMenu::for(auth()->user()) as $entry)
        @if ($entry['children'] === [])
            <flux:sidebar.item :icon="$entry['item']['icon']" :href="route($entry['item']['route'])"
                :current="$isActive($entry['item'])" wire:navigate data-test="nav-{{ $entry['item']['key'] }}">
                {{ __($entry['item']['label']) }}
            </flux:sidebar.item>
        @else
            <flux:sidebar.group expandable :heading="__($entry['item']['label'])" class="grid"
                data-test="nav-group-{{ $entry['item']['key'] }}" data-menu-key="{{ $entry['item']['key'] }}"
                :data-contains-current="$containsCurrent($entry)">
                @foreach ($entry['children'] as $child)
                    @if ($child['children'] === [])
                        <flux:sidebar.item :icon="$child['item']['icon']" :href="route($child['item']['route'])"
                            :current="$isActive($child['item'])" wire:navigate data-test="nav-{{ $child['item']['key'] }}">
                            {{ __($child['item']['label']) }}
                        </flux:sidebar.item>
                    @else
                        <flux:sidebar.group expandable :heading="__($child['item']['label'])" class="grid"
                            data-test="nav-group-{{ $child['item']['key'] }}" data-menu-key="{{ $child['item']['key'] }}"
                            :data-contains-current="$containsCurrent($child)">
                            @foreach ($child['children'] as $link)
                                <flux:sidebar.item :icon="$link['item']['icon']" :href="route($link['item']['route'])"
                                    :current="$isActive($link['item'])" wire:navigate data-test="nav-{{ $link['item']['key'] }}">
                                    {{ __($link['item']['label']) }}
                                </flux:sidebar.item>
                            @endforeach
                        </flux:sidebar.group>
                    @endif
                @endforeach
            </flux:sidebar.group>
        @endif
    @endforeach
</div>
{{--
    Groups the visitor folded stay folded from page to page, kept in this browser. The group holding the
    current page stays open without changing what is kept. Runs before paint and closes a group both
    ways: through the attributes Flux reads when it boots the disclosure, and through its `value`
    property when it has booted it already.
--}}
<script>
    (() => {
        const menu = document.currentScript.previousElementSibling;
        const storageKey = 'basics13.menu.closed';
        let closed = [];

        try {
            closed = JSON.parse(localStorage.getItem(storageKey) ?? '[]');
        } catch {}

        menu.querySelectorAll('ui-disclosure[data-menu-key]').forEach((disclosure) => {
            if (! closed.includes(disclosure.dataset.menuKey) || disclosure.hasAttribute('data-contains-current')) {
                return;
            }

            disclosure.removeAttribute('open');
            disclosure.querySelector(':scope > div')?.removeAttribute('data-open');

            if (disclosure.value === true) {
                disclosure.value = false;
            }
        });

        menu.addEventListener('click', (event) => {
            const button = event.target.closest('ui-disclosure[data-menu-key] > button');

            if (! button) {
                return;
            }

            requestAnimationFrame(() => {
                const key = button.parentElement.dataset.menuKey;
                closed = closed.filter((folded) => folded !== key);

                if (! button.parentElement.value) {
                    closed.push(key);
                }

                try {
                    localStorage.setItem(storageKey, JSON.stringify(closed));
                } catch {}
            });
        });
    })();
</script>
