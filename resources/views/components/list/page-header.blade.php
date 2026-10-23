{{--
    Page heading for resource lists: breadcrumbs, H1 with its one-line description, the actions
    (secondary first, the primary one on the far right) and the state tabs. Rendered outside the
    "list-results" fragment so it stays stable while the results morph on search. The search, the
    advanced search and its filter badges live in the toolbar of the table (`list.search`).

    The breadcrumbs only show when there is a hierarchy to walk back (archived, trash): on the
    active list they would just repeat the H1.
--}}
@props(['list', 'prefix', 'description' => null])

<div {{ $attributes->class('flex flex-col gap-4') }}>
    <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-3">
        <div class="flex min-w-0 flex-col gap-1">
            @if (count($list['breadcrumbs']) > 1)
                <flux:breadcrumbs class="text-sm text-zinc-600 dark:text-zinc-400" :data-test="$prefix.'-breadcrumbs'">
                    @foreach ($list['breadcrumbs'] as $breadcrumb)
                        @if ($loop->last)
                            {{-- The current page links to its own URL, search included. --}}
                            <flux:breadcrumbs.item :href="request()->fullUrl()" aria-current="page"
                                class="transition-colors hover:text-zinc-800 dark:hover:text-zinc-200">
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
            @endif

            <flux:heading level="1" size="xl" class="font-display text-3xl! font-semibold! tracking-tight"
                :data-test="$prefix.'-heading'">
                {{ $list['resource'] }}
            </flux:heading>

            @if ($description)
                <flux:text class="text-zinc-600 dark:text-zinc-400" :data-test="$prefix.'-description'">
                    {{ $description }}
                </flux:text>
            @endif
        </div>

        @if (isset($actions) && $actions->isNotEmpty())
            <div class="flex shrink-0 items-center gap-2">
                {{ $actions }}
            </div>
        @endif
    </div>

    <x-basics13::list.tabs :tabs="$list['tabs']" :prefix="$prefix" />
</div>