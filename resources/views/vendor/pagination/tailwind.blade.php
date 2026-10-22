{{--
    Always rendered, so the "n–m / total" summary shows even on a single page. On small screens only
    the previous/next arrows sit next to it; the page numbers appear from "sm" up. Focus uses the
    global focus token, so no link carries its own focus ring.
--}}
@php
    $link = 'inline-flex h-9 min-w-9 items-center justify-center border border-zinc-200 bg-white px-3 text-sm font-medium text-zinc-700 transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800';
    $disabled = 'inline-flex h-9 min-w-9 items-center justify-center border border-zinc-200 bg-zinc-50 px-3 text-sm text-zinc-400 cursor-not-allowed dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-500';
    $current = 'inline-flex h-9 min-w-9 items-center justify-center border border-zinc-200 bg-brand-50 px-3 text-sm font-semibold text-brand-700 cursor-default dark:border-zinc-700 dark:bg-brand-400/10 dark:text-brand-300';
@endphp

<div class="flex flex-wrap items-center justify-between gap-3">
    <p class="text-sm text-zinc-600 tabular-nums dark:text-zinc-400">
        {{ __('basics13::messages.pagination_summary', [
            'first' => $paginator->firstItem() ?? 0,
            'last' => $paginator->lastItem() ?? 0,
            'total' => $paginator->total(),
        ]) }}
    </p>

    <span class="inline-flex -space-x-px rtl:flex-row-reverse">
        {{-- Previous Page Link --}}
        @if ($paginator->onFirstPage())
            <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                <span class="{{ $disabled }} rounded-s-lg" aria-hidden="true">
                    <flux:icon.chevron-left variant="mini" />
                </span>
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $link }} rounded-s-lg"
                aria-label="{{ __('pagination.previous') }}">
                <flux:icon.chevron-left variant="mini" />
            </a>
        @endif

        {{-- Pagination Elements --}}
        @foreach ($elements as $element)
            {{-- "Three Dots" Separator --}}
            @if (is_string($element))
                <span aria-disabled="true" class="hidden sm:inline-flex">
                    <span class="{{ $disabled }} cursor-default">{{ $element }}</span>
                </span>
            @endif

            {{-- Array Of Links --}}
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span aria-current="page">
                            <span class="{{ $current }} tabular-nums">{{ $page }}</span>
                        </span>
                    @else
                        <a href="{{ $url }}" class="{{ $link }} hidden tabular-nums sm:inline-flex"
                            aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                            {{ $page }}
                        </a>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Next Page Link --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $link }} rounded-e-lg"
                aria-label="{{ __('pagination.next') }}">
                <flux:icon.chevron-right variant="mini" />
            </a>
        @else
            <span aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                <span class="{{ $disabled }} rounded-e-lg" aria-hidden="true">
                    <flux:icon.chevron-right variant="mini" />
                </span>
            </span>
        @endif
    </span>
</div>
