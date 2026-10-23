{{--
    Card holding a resource list: the optional toolbar (`list.search`) on top, the table below and
    the pagination under the card. Only the table scrolls sideways; the toolbar keeps its width.
--}}
@props(['prefix', 'paginator'])

<div {{ $attributes->merge(['class' => 'flex flex-col gap-6']) }}>
    <div class="resource-list-table overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-card dark:border-zinc-800 dark:bg-zinc-900">
        @isset($toolbar)
            <div class="border-b border-zinc-200 p-4 sm:p-5 dark:border-zinc-800" data-test="{{ $prefix }}-toolbar">
                {{ $toolbar }}
            </div>
        @endisset

        <div class="overflow-x-auto">
            <flux:table>
                {{ $slot }}
            </flux:table>
        </div>
    </div>

    <nav aria-label="{{ __('Pagination') }}" data-test="{{ $prefix }}-pagination">
        {{ $paginator->links('basics13::vendor.pagination.tailwind') }}
    </nav>
</div>
