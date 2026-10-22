@php
    $narrowed = $search !== '' || $filters !== [];
    $summary = [
        'resource' => __('Permissions summary'),
        'breadcrumbs' => [
            ['label' => __('Users'), 'url' => route('users.index')],
            ['label' => __('Permissions summary'), 'url' => null],
        ],
        'tabs' => [],
        'search' => [
            'action' => route('users.permissions-summary.index'),
            'value' => $search,
            'placeholder' => __('basics13::messages.search_record'),
        ],
    ];
@endphp

<x-layouts::app :title="$summary['resource']">
    <div class="flex flex-col gap-y-2 sm:gap-y-3">
        <x-basics13::list.page-header :list="$summary" prefix="permission-summary" scope="active"
            :description="__('What each account can do through its active roles.')"
            :searchableFields="$searchableFields" :filters="$filters">
            <x-slot:actions>
                {{-- The export carries the search and the filters, so it holds the rows on screen. --}}
                <flux:button variant="primary" icon="arrow-down-tray" :href="$exportUrl"
                    data-test="permission-summary-export">
                    {{ __('Export CSV') }}
                </flux:button>
            </x-slot:actions>
        </x-basics13::list.page-header>

        @fragment('list-results')
            <x-basics13::list.searchable-results :search="$summary['search']" :total="$users->total()" :filters="$filters">
                <x-basics13::list.search :search="$summary['search']" prefix="permission-summary" />

                <x-basics13::list.table prefix="permission-summary" :paginator="$users">
                    <flux:table.columns>
                        <flux:table.column scope="col">{{ __('Name') }}</flux:table.column>
                        <flux:table.column scope="col">{{ __('Assigned roles') }}</flux:table.column>
                        @foreach ($modules as $module => $label)
                            <flux:table.column scope="col">{{ $label }}</flux:table.column>
                        @endforeach
                        {{-- The card layout of small screens drops the label of the last cell, which is
                             meant for row actions; this empty one takes that place so every module keeps
                             its label. --}}
                        <flux:table.column scope="col"><span class="sr-only">{{ __('Actions') }}</span></flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($rows as $row)
                            <flux:table.row :key="$row['id']" data-test="permission-summary-row-{{ $row['id'] }}">
                                <flux:table.cell class="max-w-[16rem]">
                                    <span class="block truncate font-medium" title="{{ $row['name'] }}">{{ $row['name'] }}</span>
                                    <span class="block truncate text-sm text-zinc-600 dark:text-zinc-400"
                                        title="{{ $row['email'] }}">{{ $row['email'] }}</span>
                                </flux:table.cell>
                                <flux:table.cell data-test="permission-summary-roles-{{ $row['id'] }}">
                                    @if ($row['roles'] === [])
                                        <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No role') }}</span>
                                    @else
                                        <span class="flex flex-wrap justify-end gap-1 sm:justify-start">
                                            @foreach ($row['roles'] as $role)
                                                <flux:badge size="sm">{{ $role }}</flux:badge>
                                            @endforeach
                                        </span>
                                    @endif
                                </flux:table.cell>
                                @foreach ($row['modules'] as $module => $access)
                                    <flux:table.cell data-test="permission-summary-{{ $module }}-{{ $row['id'] }}">
                                        @if ($access['full'])
                                            <flux:badge size="sm" color="green" icon="check-badge">{{ __('Full access') }}</flux:badge>
                                        @elseif ($access['permissions'] === [])
                                            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No access') }}</span>
                                        @else
                                            <span class="flex flex-wrap justify-end gap-1 sm:justify-start">
                                                @foreach ($access['permissions'] as $permission)
                                                    <flux:badge size="sm" color="zinc">{{ $permission }}</flux:badge>
                                                @endforeach
                                            </span>
                                        @endif
                                    </flux:table.cell>
                                @endforeach
                                <flux:table.cell data-empty></flux:table.cell>
                            </flux:table.row>
                        @empty
                            <x-basics13::list.empty-state :narrowed="$narrowed" :colspan="3 + count($modules)"
                                :message="$narrowed ? __('basics13::messages.no_matching_records') : __('basics13::messages.no_records')" />
                        @endforelse
                    </flux:table.rows>
                </x-basics13::list.table>
            </x-basics13::list.searchable-results>
        @endfragment

        <livewire:basics13.advanced-search-modal :fields="$searchableFields" :filters="$filters" scope="active" />
    </div>
</x-layouts::app>
