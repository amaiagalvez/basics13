{{--
    Row action buttons built by the *ListTransformer classes.
    "form-modal" actions open the "{prefix}-form" drawer and pass $action['payload'] to the
    Alpine $editHandler; "modal" actions open the modal they name and pass their payload to the
    Alpine handler they name; any other action opens the "{prefix}-confirm" modal.
    Each action can supply its own hint without replacing the tooltips of other actions.
    A row with three actions or more keeps the first one as a button and groups the rest in a
    "more actions" menu.
--}}
@props(['actions', 'prefix', 'editHandler'])

@php
    $grouped = count($actions) >= 3 ? array_slice($actions, 1) : [];
    $inline = $grouped === [] ? $actions : array_slice($actions, 0, 1);
@endphp

<div class="flex justify-end gap-0.5">
    @foreach ($inline as $action)
        @if ($action['type'] === 'form-modal')
            <flux:modal.trigger :name="$prefix.'-form'">
                <flux:tooltip :content="$action['label']">
                    <flux:button size="sm" square variant="ghost" :icon="$action['icon']"
                        :aria-label="$action['label']"
                        x-on:click="{{ $editHandler }}(JSON.parse($el.dataset.payload))"
                        data-payload="{{ json_encode($action['payload']) }}"
                        :data-test="$action['test']" />
                </flux:tooltip>
            </flux:modal.trigger>
        @elseif ($action['type'] === 'modal')
            <flux:modal.trigger :name="$action['modal']">
                <flux:tooltip :content="$action['label']">
                    <flux:button size="sm" square variant="ghost" :icon="$action['icon']"
                        :aria-label="$action['label']"
                        x-on:click="{{ $action['handler'] }}(JSON.parse($el.dataset.payload))"
                        data-payload="{{ json_encode($action['payload']) }}"
                        :data-test="$action['test']" />
                </flux:tooltip>
            </flux:modal.trigger>
        @elseif ($action['type'] === 'blocked')
            <flux:tooltip :content="$action['hint']">
                <span role="note" tabindex="0" class="inline-flex" aria-label="{{ $action['hint'] }}">
                    <flux:button size="sm" square variant="ghost" :icon="$action['icon']"
                        :aria-label="$action['label']" disabled :data-test="$action['test']" />
                </span>
            </flux:tooltip>
        @else
            <flux:modal.trigger :name="$prefix.'-confirm'">
                <flux:tooltip :content="$action['hint'] ?? $action['label']">
                    <flux:button size="sm" square variant="ghost" :icon="$action['icon']"
                        :aria-label="$action['label']"
                        :class="($action['danger'] ?? false) ? 'text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300' : ''"
                        x-on:click="confirmAction(JSON.parse($el.dataset.action))"
                        data-action="{{ json_encode($action) }}" :data-test="$action['test']" />
                </flux:tooltip>
            </flux:modal.trigger>
        @endif
    @endforeach

    @if ($grouped !== [])
        <flux:dropdown position="bottom" align="end">
            <flux:tooltip :content="__('More actions')">
                <flux:button size="sm" square variant="ghost" icon="ellipsis-horizontal"
                    :aria-label="__('More actions')"
                    :data-test="$prefix.'-more-'.\Illuminate\Support\Str::afterLast($actions[0]['test'], '-')" />
            </flux:tooltip>

            <flux:menu>
                @foreach ($grouped as $action)
                    @if ($action['type'] === 'form-modal')
                        <flux:modal.trigger :name="$prefix.'-form'">
                            <flux:menu.item :icon="$action['icon']"
                                x-on:click="{{ $editHandler }}(JSON.parse($el.dataset.payload))"
                                data-payload="{{ json_encode($action['payload']) }}"
                                :data-test="$action['test']">
                                {{ $action['label'] }}
                            </flux:menu.item>
                        </flux:modal.trigger>
                    @elseif ($action['type'] === 'modal')
                        <flux:modal.trigger :name="$action['modal']">
                            <flux:menu.item :icon="$action['icon']"
                                x-on:click="{{ $action['handler'] }}(JSON.parse($el.dataset.payload))"
                                data-payload="{{ json_encode($action['payload']) }}"
                                :data-test="$action['test']">
                                {{ $action['label'] }}
                            </flux:menu.item>
                        </flux:modal.trigger>
                    @elseif ($action['type'] === 'blocked')
                        <flux:menu.item :icon="$action['icon']" disabled :data-test="$action['test']">
                            {{ $action['label'] }}: {{ $action['hint'] }}
                        </flux:menu.item>
                    @else
                        <flux:modal.trigger :name="$prefix.'-confirm'">
                            <flux:menu.item :icon="$action['icon']"
                                :variant="($action['danger'] ?? false) ? 'danger' : null"
                                x-on:click="confirmAction(JSON.parse($el.dataset.action))"
                                data-action="{{ json_encode($action) }}" :data-test="$action['test']">
                                {{ $action['label'] }}
                            </flux:menu.item>
                        </flux:modal.trigger>
                    @endif
                @endforeach
            </flux:menu>
        </flux:dropdown>
    @endif
</div>
