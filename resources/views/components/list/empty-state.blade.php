@props(['colspan', 'message'])

<!-- Empty state with card view for mobile -->
@if (request()->is('mobile') || request()->is('*.mobile'))
    <flux:callout icon="align-horizontal-end" variant="information">
        <div class="flex flex-col items-center gap-2">
            <flux:icon.magnifying-glass class="size-6 text-zinc-400 dark:text-zinc-500"
                aria-hidden="true" />
            <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ $message }}</span>
        </div>
    </flux:callout>
@else
    <flux:table.row>
        <flux:table.cell :colspan="$colspan" class="py-8 text-center text-zinc-500" data-test="list-empty-state">
            <div class="flex flex-col items-center gap-3 px-4 py-4">
                <flux:icon.magnifying-glass class="size-8 text-zinc-400 dark:text-zinc-500"
                    aria-hidden="true" />
                <span>{{ $message }}</span>
                {{ $slot }}
            </div>
        </flux:table.cell>
    </flux:table.row>
@endif