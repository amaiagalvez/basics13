{{--
    Advanced Search Modal Component.
    Receives searchable field configuration via Livewire component props.
--}}
<flux:modal wire:model="show" name="advanced-search" aria-labelledby="advanced-search-heading" flyout variant="floating"
    :closable="false"
    position="right" class="form-drawer max-w-none"
    x-on:close="window.resetTrackedForms($el)"
    x-on:cancel.prevent="window.requestTrackedModalClose($el)">
    <form wire:submit="applyFilters" class="flex flex-col h-full">
        <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-700 px-4 py-3">
            <flux:heading level="2" id="advanced-search-heading" class="text-lg font-semibold text-zinc-900 dark:text-white">
                {{ __('basics13::messages.advanced_search') }}
            </flux:heading>
            <flux:button type="button" variant="ghost" size="sm" icon="x-mark" wire:click="$dispatch('modal-close', { name: 'advanced-search' })"
                :aria-label="__('basics13::messages.close')"
                data-test="advanced-search-close"
                class="text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200" />
        </div>

        <div class="flex-1 overflow-y-auto px-4 py-4 space-y-6">
            {{-- Advanced fields toggle --}}
            @php
                $hasHiddenFields = collect($fields)->contains(fn ($f) => !($f['visible'] ?? true));
            @endphp
            @if ($hasHiddenFields)
                <div class="flex items-center gap-2 px-2">
                    <flux:button type="button" variant="ghost" size="sm" wire:click="toggleAdvanced"
                        :aria-expanded="$showAdvanced ? 'true' : 'false'"
                        data-test="advanced-search-toggle"
                        class="text-sm text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white">
                        {{ $showAdvanced ? __('basics13::messages.hide_advanced_fields') : __('basics13::messages.show_advanced_fields') }}
                    </flux:button>
                </div>
            @endif

            {{-- Search fields --}}
            @foreach ($this->getVisibleFields() as $fieldKey => $field)
                @php
                    $isVisible = ($field['visible'] ?? true);
                    $showField = $isVisible || $showAdvanced;
                    $operators = $this->getOperatorsForField($field);
                    $defaultOperator = $field['default_operator'] ?? ($operators ? array_key_first($operators) : 'contains');
                    $currentFilter = $filters[$fieldKey] ?? ['operator' => $defaultOperator, 'value' => null];
                    $currentOperator = $currentFilter['operator'] ?? $defaultOperator;
                    $currentValue = $currentFilter['value'] ?? null;
                    $allowsIsEmpty = $this->fieldAllowsIsEmpty($field);
                    $isMultiSelect = $this->fieldIsMultiSelect($field);
                    $selectOptions = $this->getSelectOptions($field);
                    $fieldType = $field['type'] ?? 'text';
                @endphp

                <div class="space-y-2" x-show="{{ $showField ? 'true' : 'false' }}">
                    {{-- Operator select --}}
                    <flux:field>
                        <div class="flex items-center gap-1">
                            <flux:label>{{ __($field['label']) }}</flux:label>
                            @isset($field['help'])
                                <flux:tooltip toggleable :content="__($field['help'])">
                                    <flux:button type="button" icon="information-circle" size="xs" variant="ghost"
                                        :aria-label="__($field['help'])" :data-test="$fieldKey.'-advanced-search-info'" />
                                </flux:tooltip>
                            @endisset
                        </div>
                        <div @class(['flex gap-2', 'items-start' => $isMultiSelect])>
                            {{-- Live: a date operator switches between one input and a from/to pair. --}}
                            <flux:select wire:model.live="filters.{{ $fieldKey }}.operator" class="w-36 shrink-0"
                                :data-test="$fieldKey.'-advanced-search-operator'"
                                :aria-label="__('basics13::messages.filter_operator')">
                                @if ($fieldType === 'boolean')
                                    <option value="">{{ __('basics13::messages.any_value') }}</option>
                                @endif
                                @foreach ($operators as $opKey => $opLabel)
                                    <option value="{{ $opKey }}">{{ $opLabel }}</option>
                                @endforeach
                            </flux:select>

                            {{-- Value input based on field type --}}
                            @switch($fieldType)
                                @case('text')
                                    <flux:input wire:model="filters.{{ $fieldKey }}.value"
                                        :aria-label="__($field['label'])"
                                        placeholder="{{ isset($field['placeholder']) ? __($field['placeholder']) : '' }}"
                                        class="flex-1 min-w-0" />
                                    @break
                                @case('number')
                                    <flux:input type="number" wire:model="filters.{{ $fieldKey }}.value"
                                        :aria-label="__($field['label'])"
                                        placeholder="{{ isset($field['placeholder']) ? __($field['placeholder']) : '' }}"
                                        class="flex-1 min-w-0" step="any" />
                                    @break
                                @case('date')
                                    @if ($currentOperator === 'between')
                                        <div class="flex gap-2 flex-1 min-w-0">
                                            <flux:input type="date" wire:model="filters.{{ $fieldKey }}.value.0"
                                                :aria-label="__($field['label']).' – '.__('basics13::messages.date_from')"
                                                placeholder="{{ __('basics13::messages.date_from') }}"
                                                class="flex-1 min-w-0" />
                                            <flux:input type="date" wire:model="filters.{{ $fieldKey }}.value.1"
                                                :aria-label="__($field['label']).' – '.__('basics13::messages.date_to')"
                                                placeholder="{{ __('basics13::messages.date_to') }}"
                                                class="flex-1 min-w-0" />
                                        </div>
                                    @else
                                        <flux:input type="date" wire:model="filters.{{ $fieldKey }}.value"
                                            :aria-label="__($field['label'])"
                                            placeholder="{{ isset($field['placeholder']) ? __($field['placeholder']) : '' }}"
                                            class="flex-1 min-w-0" />
                                    @endif
                                    @break
                                @case('select')
                                    @if ($isMultiSelect)
                                        {{-- Same checkbox list as the resource forms: every option visible, no Ctrl/Cmd. --}}
                                        <div role="group" aria-label="{{ __($field['label']) }}"
                                            data-test="{{ $fieldKey }}-advanced-search-options"
                                            class="max-h-64 min-w-0 flex-1 divide-y divide-zinc-100 overflow-y-auto rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white shadow-xs dark:divide-white/5 dark:border-white/10 dark:bg-white/10">
                                            @foreach ($selectOptions as $optValue => $optLabel)
                                                <label wire:key="{{ $fieldKey }}-option-{{ $optValue }}"
                                                    class="flex cursor-pointer items-center gap-3 px-3 py-2.5 text-sm text-zinc-700 transition-colors hover:bg-zinc-50 has-[:checked]:bg-brand-50 dark:text-zinc-300 dark:hover:bg-white/5 dark:has-[:checked]:bg-brand-500/10">
                                                    <input type="checkbox" value="{{ $optValue }}"
                                                        wire:model="filters.{{ $fieldKey }}.value"
                                                        class="size-4 shrink-0 rounded accent-brand-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus dark:accent-brand-400" />
                                                    <span class="min-w-0 flex-1 truncate">{{ $optLabel }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    @else
                                        <flux:select wire:model="filters.{{ $fieldKey }}.value"
                                            class="flex-1 min-w-0" :aria-label="__($field['label'])">
                                            <option value="">{{ __('basics13::messages.select_placeholder') }}</option>
                                            @foreach ($selectOptions as $optValue => $optLabel)
                                                <option value="{{ $optValue }}">{{ $optLabel }}</option>
                                            @endforeach
                                        </flux:select>
                                    @endif
                                    @break
                            @endswitch
                        </div>

                    </flux:field>
                </div>
            @endforeach
        </div>

        {{-- Footer actions --}}
        <div class="flex items-center justify-end gap-2 border-t border-zinc-200 dark:border-zinc-700 px-4 py-3">
            <flux:button type="button" variant="ghost" wire:click="clearFilters"
                wire:loading.attr="disabled">
                {{ __('basics13::messages.clear_filters') }}
            </flux:button>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                {{ __('basics13::messages.apply_filters') }}
            </flux:button>
        </div>
    </form>
</flux:modal>