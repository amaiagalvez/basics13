{{--
    State tabs (resource / archived / trash) built by the *ListTransformer classes, each one with
    the number of records it holds. The current tab links to the full URL, search included, so it
    always describes the view being shown.

    $label names the group of tabs for screen readers; it defaults to the resource lists, which are
    the only other place these are used from.
--}}
@props(['tabs', 'prefix', 'label' => null])

<nav aria-label="{{ $label ?? __('List views') }}" data-test="{{ $prefix }}-tabs"
    class="flex flex-wrap items-center gap-2">
    @foreach ($tabs as $tab)
        <a href="{{ $tab['current'] ? request()->fullUrl() : $tab['url'] }}" wire:navigate
            data-test="{{ $tab['test'] }}"
            @if ($tab['current']) aria-current="page" @endif
            class="inline-flex h-11 items-center gap-2 rounded-full border px-4 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-brand-600 focus-visible:ring-offset-2 focus-visible:outline-none sm:h-9 {{ $tab['current'] ? 'border-transparent bg-brand-700 text-white dark:bg-brand-400 dark:text-zinc-950' : 'border-zinc-200 bg-white text-zinc-700 hover:border-zinc-300 hover:bg-zinc-50 hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-white' }}">
            <span>{{ $tab['label'] }}</span>
            @if ($tab['count'] !== null)
                <span
                    class="text-xs font-medium tabular-nums {{ $tab['current'] ? 'text-white/80 dark:text-zinc-950/75' : 'text-zinc-600 dark:text-zinc-400' }}">{{ $tab['count'] }}</span>
            @endif
        </a>
    @endforeach

    @isset($actions)
        <div class="ms-auto self-center">
            {{ $actions }}
        </div>
    @endisset
</nav>
