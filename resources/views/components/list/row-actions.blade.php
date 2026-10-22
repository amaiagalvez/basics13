{{--
    Row action buttons built by the *ListTransformer classes.
    "form-modal" actions open the "{prefix}-form" drawer and pass $action['payload'] to the
    Alpine $editHandler; any other action opens the "{prefix}-confirm" modal.
    Each action can supply its own hint without replacing the tooltips of other actions.
--}}
@props(['actions', 'prefix', 'editHandler'])

<!-- Render actions with "⋯" menu when there are more than 2 -->
@php
    $actionCount = count($actions);
    $showMoreMenu = $actionCount > 2;
@endphp

@if ($showMoreMenu)
    <!-- Show "⋯" menu button for >2 actions -->
    <flux:menu>
        <flux:menu.trigger as="button" size="icon" variant="ghost">
            <flux:icon>more-horizontal</flux:icon>
        </flux:menu.trigger>
        <flux:menu.content class="p-1">
            @foreach ($actions as $index => $action)
                @if ($action['type'] === 'form-modal')
                    <flux:menu.item>
                        <flux:tooltip :content="$action['label']">
                            <flux:button size="default" variant="ghost" :icon="$action['icon']"
                                :aria-label="$action['label']"
                                x-on:click="{{ $editHandler }}(JSON.parse($el.dataset.payload))"
                                data-payload="{{ json_encode($action['payload']) }}"
                                :data-test="$action['test']">
                                {{ $action['label'] }}
                            </flux:button>
                        </flux:tooltip>
                    </flux:menu.item>
                @elseif ($action['type'] === 'blocked')
                    <flux:menu.item disabled>
                        <flux:tooltip :content="$action['hint']">
                            <span class="inline-flex">
                                <flux:button size="default" variant="ghost" :icon="$action['icon']"
                                    :aria-label="$action['label']" disabled>
                                    {{ $action['label'] }}
                                </flux:button>
                            </span>
                        </flux:tooltip>
                    </flux:menu.item>
                @else
                    <flux:menu.item>
                        <flux:tooltip :content="$action['hint'] ?? $action['label']">
                            <flux:button size="default" variant="ghost" :icon="$action['icon']"
                                :aria-label="$action['label']"
                                x-on:click="confirmAction(JSON.parse($el.dataset.action))"
                                data-action="{{ json_encode($action) }}">
                                {{ $action['label'] }}
                            </flux:button>
                        </flux:tooltip>
                    </flux:menu.item>
                @endif
            @endforeach
        </flux:menu.content>
    </flux:menu>
@else
    <div class="flex justify-end gap-0.5">
        @foreach ($actions as $action)
            @if ($action['type'] === 'form-modal')
                <flux:modal.trigger :name="$prefix.'-form'">
                    <flux:tooltip :content="$action['label']">
                        <flux:button size="xs" square variant="ghost" :icon="$action['icon']"
                            :aria-label="$action['label']"
                            x-on:click="{{ $editHandler }}(JSON.parse($el.dataset.payload))"
                            data-payload="{{ json_encode($action['payload']) }}"
                            :data-test="$action['test']" />
                    </flux:tooltip>
                </flux:modal.trigger>
            @elseif ($action['type'] === 'blocked')
                <flux:tooltip :content="$action['hint']">
                    <span role="note" tabindex="0" class="inline-flex" aria-label="{{ $action['hint'] }}">
                        <flux:button size="xs" square variant="ghost" :icon="$action['icon']"
                            :aria-label="$action['label']" disabled :data-test="$action['test']" />
                    </span>
                </flux:tooltip>
            @else
                <flux:modal.trigger :name="$prefix.'-confirm'">
                    <flux:tooltip :content="$action['hint'] ?? $action['label']">
                        <flux:button size="xs" square variant="ghost" :icon="$action['icon']"
                            :aria-label="$action['label']"
                            :class="($action['danger'] ?? false) ? 'text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300' : ''"
                            x-on:click="confirmAction(JSON.parse($el.dataset.action))"
                            data-action="{{ json_encode($action) }}" :data-test="$action['test']" />
                    </flux:tooltip>
                </flux:modal.trigger>
            @endif
        @endforeach
    </div>
@endif