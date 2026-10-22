{{--
    Empty list: one icon, one line and, through the slot, the create button. A search that found
    nothing shows a magnifier; an empty list shows a tray.
--}}
@props(['colspan', 'message', 'narrowed' => false])

<flux:table.row>
    <flux:table.cell :colspan="$colspan" class="py-12 text-center" data-test="list-empty-state">
        <div class="flex flex-col items-center gap-3 px-4">
            <flux:icon :icon="$narrowed ? 'magnifying-glass' : 'inbox'" class="size-8 text-zinc-400 dark:text-zinc-500"
                aria-hidden="true" />
            <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $message }}</p>
            {{ $slot }}
        </div>
    </flux:table.cell>
</flux:table.row>
