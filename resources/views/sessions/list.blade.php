@php
    $narrowed = $search !== '' || $filters !== [];
    $list = [
        'resource' => __('Sessions'),
        'breadcrumbs' => [
            ['label' => __('Sessions'), 'url' => null],
        ],
        'tabs' => [],
        'search' => [
            'action' => route('sessions.index'),
            'value' => $search,
            'placeholder' => __('basics13::messages.search_record'),
        ],
    ];
    $eventColors = ['login' => 'green', 'failed' => 'amber', 'lockout' => 'red'];
@endphp

<x-layouts::app :title="$list['resource']">
    <div class="flex flex-col gap-y-2 sm:gap-y-3">
        <x-basics13::list.page-header :list="$list" prefix="session"
            :description="__('Every sign-in, failed attempt and lockout, newest first. The register cannot be changed.')" />

        @fragment('list-results')
            <x-basics13::list.searchable-results :search="$list['search']" :total="$sessions->total()" :filters="$filters">
                <x-basics13::list.table prefix="session" :paginator="$sessions">
                    <x-slot:toolbar>
                        <x-basics13::list.search :search="$list['search']" prefix="session" scope="active" :searchableFields="$searchableFields" :filters="$filters" />
                    </x-slot:toolbar>

                    <flux:table.columns>
                        <flux:table.column scope="col">{{ __('Date') }}</flux:table.column>
                        <flux:table.column scope="col">{{ __('Result') }}</flux:table.column>
                        <flux:table.column scope="col">{{ __('Typed login') }}</flux:table.column>
                        <flux:table.column scope="col">{{ __('User') }}</flux:table.column>
                        <flux:table.column scope="col">{{ __('IP address') }}</flux:table.column>
                        <flux:table.column scope="col">{{ __('Browser') }}</flux:table.column>
                        <flux:table.column scope="col">{{ __('Signed out') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($rows as $row)
                            <flux:table.row :key="$row['id']" data-test="session-row-{{ $row['id'] }}">
                                <flux:table.cell class="whitespace-nowrap">
                                    <x-basics13::list.local-time :datetime="$row['createdAt']" />
                                </flux:table.cell>
                                <flux:table.cell data-test="session-result-{{ $row['id'] }}">
                                    <span class="flex flex-col items-end gap-0.5 sm:items-start">
                                        <flux:badge size="sm" :color="$eventColors[$row['event']] ?? 'zinc'">{{ $row['eventLabel'] }}</flux:badge>
                                        @if ($row['reason'] !== null)
                                            <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ $row['reason'] }}</span>
                                        @elseif ($row['lockoutMinutes'] !== null)
                                            <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Locked for :minutes minutes', ['minutes' => $row['lockoutMinutes']]) }}</span>
                                        @endif
                                    </span>
                                </flux:table.cell>
                                <flux:table.cell class="max-w-[14rem]">
                                    <span class="block truncate" title="{{ $row['login'] }}">{{ $row['login'] }}</span>
                                </flux:table.cell>
                                <flux:table.cell class="max-w-[12rem]">
                                    @if ($row['user'] !== null)
                                        <span class="block truncate" title="{{ $row['user'] }}">{{ $row['user'] }}</span>
                                        @if ($row['userTrashed'])
                                            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('basics13::messages.trash_label') }}</span>
                                        @endif
                                        @if ($row['impersonator'] !== null)
                                            <flux:badge size="sm" color="amber" data-test="session-impersonator-{{ $row['id'] }}">{{ __('Impersonated by :name', ['name' => $row['impersonator']]) }}</flux:badge>
                                        @endif
                                    @else
                                        <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Unknown login') }}</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="whitespace-nowrap font-mono text-sm">{{ $row['ip'] }}</flux:table.cell>
                                <flux:table.cell class="max-w-[16rem]">
                                    <span class="block truncate text-sm" title="{{ $row['userAgent'] }}" data-test="session-agent-{{ $row['id'] }}">{{ $row['userAgent'] }}</span>
                                </flux:table.cell>
                                <flux:table.cell class="whitespace-nowrap">
                                    @if ($row['loggedOutAt'] !== null)
                                        <x-basics13::list.local-time :datetime="$row['loggedOutAt']" />
                                    @elseif ($row['event'] === 'login')
                                        <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Still signed in') }}</span>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <x-basics13::list.empty-state :narrowed="$narrowed" :colspan="7"
                                :message="$narrowed ? __('basics13::messages.no_matching_records') : __('basics13::messages.no_records')" />
                        @endforelse
                    </flux:table.rows>
                </x-basics13::list.table>
            </x-basics13::list.searchable-results>
        @endfragment

        <livewire:basics13.advanced-search-modal :fields="$searchableFields" :filters="$filters" scope="active" />
    </div>
</x-layouts::app>
