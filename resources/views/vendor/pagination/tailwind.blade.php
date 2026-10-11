{{-- Always render pagination to show total count and page info even for single page --}}
<div>

    <div class="flex gap-2 items-center justify-between sm:hidden">

            @if ($paginator->onFirstPage())
                <span class="inline-flex items-center px-4 py-2 text-sm font-medium text-zinc-600 bg-white border border-zinc-300 cursor-not-allowed leading-5 rounded-md dark:text-zinc-300 dark:bg-zinc-700 dark:border-zinc-600">
                    {!! __('pagination.previous') !!}
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center px-4 py-2 text-sm font-medium text-zinc-800 bg-white border border-zinc-300 leading-5 rounded-md hover:text-zinc-700 focus:outline-none focus:ring ring-zinc-300 focus:border-blue-300 active:bg-zinc-100 active:text-zinc-800 transition ease-in-out duration-150 dark:bg-zinc-800 dark:border-zinc-600 dark:text-zinc-200 dark:focus:border-blue-700 dark:active:bg-zinc-700 dark:active:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-900 dark:hover:text-zinc-200">
                    {!! __('pagination.previous') !!}
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex items-center px-4 py-2 text-sm font-medium text-zinc-800 bg-white border border-zinc-300 leading-5 rounded-md hover:text-zinc-700 focus:outline-none focus:ring ring-zinc-300 focus:border-blue-300 active:bg-zinc-100 active:text-zinc-800 transition ease-in-out duration-150 dark:bg-zinc-800 dark:border-zinc-600 dark:text-zinc-200 dark:focus:border-blue-700 dark:active:bg-zinc-700 dark:active:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-900 dark:hover:text-zinc-200">
                    {!! __('pagination.next') !!}
                </a>
            @else
                <span class="inline-flex items-center px-4 py-2 text-sm font-medium text-zinc-600 bg-white border border-zinc-300 cursor-not-allowed leading-5 rounded-md dark:text-zinc-300 dark:bg-zinc-700 dark:border-zinc-600">
                    {!! __('pagination.next') !!}
                </span>
            @endif

        </div>

        <div class="hidden sm:flex-1 sm:flex sm:gap-2 sm:items-center sm:justify-between">

            <div>
                <p class="text-sm text-zinc-700 leading-5 dark:text-zinc-300">
                    {{ __('basics13::messages.pagination_summary', [
                        'first' => $paginator->firstItem() ?? 0,
                        'last' => $paginator->lastItem() ?? 0,
                        'total' => $paginator->total(),
                    ]) }}
                </p>
            </div>

            <div>
                <span class="inline-flex rtl:flex-row-reverse shadow-sm rounded-md">

                    {{-- Previous Page Link --}}
                    @if ($paginator->onFirstPage())
                        <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                            <span class="inline-flex items-center px-2 py-2 text-sm font-medium text-zinc-500 bg-white border border-zinc-300 cursor-not-allowed rounded-l-md leading-5 dark:bg-zinc-700 dark:border-zinc-600 dark:text-zinc-400" aria-hidden="true">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </span>
                        </span>
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center px-2 py-2 text-sm font-medium text-zinc-500 bg-white border border-zinc-300 rounded-l-md leading-5 hover:text-zinc-400 focus:outline-none focus:ring ring-zinc-300 focus:border-blue-300 active:bg-zinc-100 active:text-zinc-500 transition ease-in-out duration-150 dark:bg-zinc-800 dark:border-zinc-600 dark:active:bg-zinc-700 dark:focus:border-blue-800 dark:text-zinc-300 dark:hover:bg-zinc-900 dark:hover:text-zinc-300" aria-label="{{ __('pagination.previous') }}">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </a>
                    @endif

                    {{-- Pagination Elements --}}
                    @foreach ($elements as $element)
                        {{-- "Three Dots" Separator --}}
                        @if (is_string($element))
                            <span aria-disabled="true">
                                <span class="inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-zinc-700 bg-white border border-zinc-300 cursor-default leading-5 dark:bg-zinc-800 dark:border-zinc-600 dark:text-zinc-300">{{ $element }}</span>
                            </span>
                        @endif

                        {{-- Array Of Links --}}
                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page">
                                        <span class="inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-zinc-700 bg-zinc-200 border border-zinc-300 cursor-default leading-5 dark:bg-zinc-700 dark:border-zinc-600 dark:text-zinc-300">{{ $page }}</span>
                                    </span>
                                @else
                                    <a href="{{ $url }}" class="inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-zinc-700 bg-white border border-zinc-300 leading-5 hover:text-zinc-700 focus:outline-none focus:ring ring-zinc-300 focus:border-blue-300 active:bg-zinc-100 active:text-zinc-700 transition ease-in-out duration-150 dark:bg-zinc-800 dark:border-zinc-600 dark:text-zinc-300 dark:hover:text-zinc-300 dark:active:bg-zinc-700 dark:focus:border-blue-800 hover:bg-zinc-100 dark:hover:bg-zinc-900" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                        {{ $page }}
                                    </a>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    {{-- Next Page Link --}}
                    @if ($paginator->hasMorePages())
                        <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex items-center px-2 py-2 -ml-px text-sm font-medium text-zinc-500 bg-white border border-zinc-300 rounded-r-md leading-5 hover:text-zinc-400 focus:outline-none focus:ring ring-zinc-300 focus:border-blue-300 active:bg-zinc-100 active:text-zinc-500 transition ease-in-out duration-150 dark:bg-zinc-800 dark:border-zinc-600 dark:active:bg-zinc-700 dark:focus:border-blue-800 dark:text-zinc-300 dark:hover:bg-zinc-900 dark:hover:text-zinc-300" aria-label="{{ __('pagination.next') }}">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                            </svg>
                        </a>
                    @else
                        <span aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                            <span class="inline-flex items-center px-2 py-2 -ml-px text-sm font-medium text-zinc-500 bg-white border border-zinc-300 cursor-not-allowed rounded-r-md leading-5 dark:bg-zinc-700 dark:border-zinc-600 dark:text-zinc-400" aria-hidden="true">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                </svg>
                            </span>
                        </span>
                    @endif
                </span>
            </div>
        </div>
    </div>