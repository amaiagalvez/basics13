@php
    $days = $summary['days'];
    $failedUrl = route('sessions.index', ['filters' => [
        'event' => ['operator' => 'equals', 'value' => ['failed']],
        'created_at' => ['operator' => 'between', 'value' => [$days[0], $days[array_key_last($days)]]],
    ]]);
    $number = static fn (float|int $value): string => (string) \Illuminate\Support\Number::format($value, maxPrecision: 1, locale: app()->getLocale());
    // Change against the window of the same length right before; none when that one was empty.
    $change = static function (array $flow) use ($number): ?array {
        if ($flow['previous'] === 0) {
            return null;
        }

        $percent = round(($flow['current'] - $flow['previous']) / $flow['previous'] * 100, 1);

        return ['percent' => $percent, 'text' => ($percent > 0 ? '+' : ($percent < 0 ? '−' : '')).$number(abs($percent))];
    };
    $canListUsers = auth()->user()->can('viewAny', config('auth.providers.users.model'));
    // `up` says whether a rise is good news, which picks the colour of the change.
    $metrics = [
        ['icon' => 'users', 'label' => __('Total users'), 'value' => $number($summary['users']), 'url' => $canListUsers ? route('users.index') : null, 'test' => 'admin-dashboard-users'],
        ['icon' => 'bolt', 'label' => __('Active users'), 'value' => $number($summary['active']['current']), 'change' => $change($summary['active']), 'up' => true, 'test' => 'admin-dashboard-active'],
        ['icon' => 'user-plus', 'label' => __('New users'), 'value' => $number($summary['new']['current']), 'change' => $change($summary['new']), 'up' => true, 'test' => 'admin-dashboard-new'],
        ['icon' => 'moon', 'label' => __('Inactive users'), 'value' => $number($summary['inactive']), 'test' => 'admin-dashboard-inactive'],
        ['icon' => 'envelope', 'label' => __('Pending verification'), 'value' => $number($summary['pending']), 'test' => 'admin-dashboard-pending'],
        ['icon' => 'arrow-trending-up', 'label' => __('Growth rate'), 'value' => $summary['growth'] === null ? '—' : __(':value%', ['value' => ($summary['growth'] > 0 ? '+' : '').$number($summary['growth'])]), 'test' => 'admin-dashboard-growth'],
        // The failed figure opens the session list narrowed to the same result and days.
        ['icon' => 'exclamation-triangle', 'label' => __('Failed sign-ins'), 'value' => $number($summary['failed']['current']), 'change' => $change($summary['failed']), 'up' => false, 'url' => $failedUrl, 'test' => 'admin-dashboard-failed'],
    ];
    $alertsData = $summary['alerts'];
    // Only the alerts whose condition holds; links only where the viewer may go.
    $alerts = array_values(array_filter([
        $alertsData['pending'] > 0 ? ['icon' => 'envelope', 'test' => 'admin-alert-pending', 'url' => $canListUsers ? route('users.index') : null,
            'text' => trans_choice('{1} One account is pending email verification.|[2,*] :count accounts are pending email verification.', $alertsData['pending'], ['count' => $alertsData['pending']])] : null,
        $alertsData['inactive'] > 0 ? ['icon' => 'moon', 'test' => 'admin-alert-inactive', 'url' => null,
            'text' => trans_choice('{1} One account has not signed in for :days days.|[2,*] :count accounts have not signed in for :days days.', $alertsData['inactive'], ['count' => $alertsData['inactive'], 'days' => $inactivityDays])] : null,
        $alertsData['surge'] ? ['icon' => 'arrow-trending-up', 'test' => 'admin-alert-surge', 'url' => $canListUsers ? route('users.index') : null,
            'text' => __('Unusual rise in sign-ups: :count new accounts in the last :days days.', ['count' => $summary['new']['current'], 'days' => $period])] : null,
        $alertsData['suspended'] > 0 ? ['icon' => 'archive-box', 'test' => 'admin-alert-suspended', 'url' => $canListUsers ? route('users.archived.index') : null,
            'text' => trans_choice('{1} One account was archived in the last :days days.|[2,*] :count accounts were archived in the last :days days.', $alertsData['suspended'], ['count' => $alertsData['suspended'], 'days' => $period])] : null,
    ]));
    $devices = array_map(static fn (\Basics13\Enums\DeviceType $device): array => [
        'label' => $device->label(),
        'count' => $summary['devices'][$device->value],
        'test' => 'admin-dashboard-device-'.$device->value,
    ], \Basics13\Enums\DeviceType::cases());
    $deviceTotal = array_sum(array_column($devices, 'count'));
    $deviceColors = ['text-brand-600 dark:text-brand-400', 'text-violet-500 dark:text-violet-400', 'text-amber-500 dark:text-amber-400'];
    $percent = static fn (int $count): string => __(':value%', ['value' => $number($deviceTotal > 0 ? round($count / $deviceTotal * 100) : 0)]);
    $card = 'rounded-2xl border border-zinc-200 bg-white shadow-card dark:border-zinc-800 dark:bg-zinc-900';
@endphp

<x-layouts::app :title="__('Administration dashboard')">
    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-col gap-1">
                <flux:heading size="xl" level="1">{{ __('Administration dashboard') }}</flux:heading>
                <flux:text>{{ __('Accounts and sign-ins over the last :days days.', ['days' => $period]) }}</flux:text>
            </div>
            <nav aria-label="{{ __('Period') }}">
                <flux:button.group>
                    @foreach ($periods as $option)
                        <flux:button size="sm" :variant="$option === $period ? 'primary' : 'outline'"
                            :href="route('sessions.dashboard', ['period' => $option])" wire:navigate
                            :aria-current="$option === $period ? 'page' : null" data-test="admin-dashboard-period-{{ $option }}">
                            {{ __(':days days', ['days' => $option]) }}
                        </flux:button>
                    @endforeach
                </flux:button.group>
            </nav>
        </div>

        @if ($alerts !== [])
            <section class="flex flex-col gap-2" aria-label="{{ __('Alerts') }}" data-test="admin-dashboard-alerts">
                @foreach ($alerts as $alert)
                    <flux:callout inline :icon="$alert['icon']" color="amber" data-test="{{ $alert['test'] }}">
                        <flux:callout.heading>{{ $alert['text'] }}</flux:callout.heading>
                        @if ($alert['url'])
                            <x-slot name="actions">
                                <flux:button size="sm" variant="ghost" :href="$alert['url']" wire:navigate>{{ __('Review') }}</flux:button>
                            </x-slot>
                        @endif
                    </flux:callout>
                @endforeach
            </section>
        @endif

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="{{ __('Summary') }}">
            @foreach ($metrics as $metric)
                @php
                    $url = $metric['url'] ?? null;
                    $delta = $metric['change'] ?? null;
                    $good = $delta !== null && ($delta['percent'] === 0.0 ? null : ($delta['percent'] > 0) === $metric['up']);
                @endphp
                {{-- A figure only links where the viewer may go. --}}
                <{{ $url ? 'a' : 'div' }} @if ($url) href="{{ $url }}" wire:navigate @endif data-test="{{ $metric['test'] }}"
                    @class(['flex items-start gap-4 p-5', $card, 'transition-shadow hover:shadow-raised' => $url])>
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-700 dark:bg-brand-400/10 dark:text-brand-300">
                        <flux:icon :icon="$metric['icon']" variant="mini" aria-hidden="true" />
                    </span>
                    <span class="flex min-w-0 flex-col">
                        <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ $metric['label'] }}</span>
                        <span class="font-display text-3xl font-semibold tabular-nums text-zinc-900 dark:text-white" data-test="{{ $metric['test'] }}-value">{{ $metric['value'] }}</span>
                        @if ($delta !== null)
                            <span data-test="{{ $metric['test'] }}-change" @class([
                                'text-sm tabular-nums',
                                'text-green-700 dark:text-green-400' => $good === true,
                                'text-red-700 dark:text-red-400' => $good === false,
                                'text-zinc-600 dark:text-zinc-400' => $good === null,
                            ])>{{ __(':change% vs the previous :days days', ['change' => $delta['text'], 'days' => $period]) }}</span>
                        @endif
                    </span>
                </{{ $url ? 'a' : 'div' }}>
            @endforeach
        </section>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="{{ $card }} flex flex-col gap-4 p-5" aria-labelledby="admin-dashboard-chart-heading">
                <h2 id="admin-dashboard-chart-heading" class="font-display text-lg font-semibold">{{ __('Sign-ins per day') }}</h2>
                <x-basics13::chart :height="280" data-test="admin-dashboard-chart"
                    :colors="['text-brand-600 dark:text-brand-400', 'text-brand-300 dark:text-brand-700']"
                    :labels="$days"
                    :series="[
                        ['name' => __('Sign-ins'), 'data' => $summary['signIns']],
                        ['name' => __('Unique users'), 'data' => $summary['uniqueUsers']],
                    ]"
                    :label="__(':signIns sign-ins over the last :days days, by up to :users unique users a day.', [
                        'signIns' => array_sum($summary['signIns']),
                        'days' => $period,
                        'users' => max([0, ...$summary['uniqueUsers']]),
                    ])" />
            </section>

            <section class="{{ $card }} flex flex-col gap-4 p-5" aria-labelledby="admin-dashboard-devices-heading" data-test="admin-dashboard-devices">
                <h2 id="admin-dashboard-devices-heading" class="font-display text-lg font-semibold">{{ __('Sessions by device') }}</h2>
                @if ($deviceTotal === 0)
                    <flux:text data-test="admin-dashboard-devices-empty">{{ __('No sign-ins in the last :days days.', ['days' => $period]) }}</flux:text>
                @else
                    <div class="relative">
                        <x-basics13::chart type="donut" :height="220" :colors="$deviceColors"
                            :labels="array_column($devices, 'label')"
                            :series="array_column($devices, 'count')"
                            :label="__(':total sign-ins over the last :days days: :split.', [
                                'total' => $deviceTotal,
                                'days' => $period,
                                'split' => implode(', ', array_map(static fn (array $device): string => $device['label'].' '.$percent($device['count']), $devices)),
                            ])" />
                        {{-- The total sits in the hole of the ring; the chart itself is hidden from assistive technology. --}}
                        <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center" aria-hidden="true">
                            <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Sessions') }}</span>
                            <span class="font-display text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white" data-test="admin-dashboard-devices-total">{{ $number($deviceTotal) }}</span>
                        </div>
                    </div>
                @endif
                <ul class="flex flex-col gap-3">
                    @foreach ($devices as $device)
                        <li class="flex items-center justify-between gap-4 text-sm" data-test="{{ $device['test'] }}">
                            <span class="flex items-center gap-2">
                                <span class="size-2.5 rounded-full bg-current {{ $deviceColors[$loop->index] }}" aria-hidden="true"></span>
                                {{ $device['label'] }}
                            </span>
                            <span class="tabular-nums text-zinc-600 dark:text-zinc-400" data-test="{{ $device['test'] }}-percent">{{ $percent($device['count']) }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>
    </div>
</x-layouts::app>
