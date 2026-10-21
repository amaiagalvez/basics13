{{--
    Advanced Search Modal Component.
    Receives searchable field configuration via Livewire component props.
--}}
<flux:modal wire:model="show" name="advanced-search" aria-labelledby="advanced-search-heading" variant="flyout"
    position="right" class="form-drawer max-w-none"
    x-on:close="window.clearForm($el.querySelector('form')); window.resetTrackedForms($el)"
    x-on:cancel.prevent="window.requestTrackedModalClose($el)">
    <form wire:submit="applyFilters" class="flex flex-col h-full">
        <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-700 px-4 py-3">
            <flux:heading level="2" id="advanced-search-heading" class="text-lg font-semibold text-zinc-900 dark:text-white">
                {{ __('basics13::messages.advanced_search') }}
            </flux:heading>
            <flux:button type="button" variant="ghost" size="sm" icon="x-mark" wire:click="$dispatch('modal-close', { name: 'advanced-search' })"
                :aria-label="__('basics13::messages.close')" class="text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200" />
        </div>

        <div class="flex-1 overflow-y-auto px-4 py-4 space-y-6">
            {{-- Advanced fields toggle --}}
            @php
                $hasHiddenFields = collect($fields)->contains(fn ($f) => !($f['visible'] ?? true));
            @endphp
            @if ($hasHiddenFields)
                <div class="flex items-center gap-2 px-2">
                    <flux:button type="button" variant="ghost" size="sm" wire:click="toggleAdvanced"
                        class="text-sm text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white">
                        {{ $showAdvanced ? __('basics13::messages.hide_advanced_fields') : __('basics13::messages.show_advanced_fields') }}
                    </flux:button>
                </div>
            @endif

            {{-- Field groups --}}
            @foreach ($this->getGroupedFields() as $groupKey => $groupFields)
                <div class="space-y-4">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 px-2">
                        {{ $this->getGroupLabel($groupKey) }}
                    </h3>

                    @foreach ($groupFields as $fieldKey => $field)
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
                                <flux:label>{{ __($field['label']) }}</flux:label>
                                <div class="flex gap-2">
                                    <flux:select wire:model="filters.{{ $fieldKey }}.operator" class="w-36 shrink-0"
                                        :aria-label="__('basics13::messages.filter_operator')">
                                        @foreach ($operators as $opKey => $opLabel)
                                            <option value="{{ $opKey }}">{{ $opLabel }}</option>
                                        @endforeach
                                    </flux:select>

                                    {{-- Value input based on field type --}}
                                    @switch($fieldType)
                                        @case('text')
                                            <flux:input wire:model="filters.{{ $fieldKey }}.value"
                                                placeholder="{{ isset($field['placeholder']) ? __($field['placeholder']) : '' }}"
                                                class="flex-1 min-w-0" />
                                            @break
                                        @case('number')
                                            <flux:input type="number" wire:model="filters.{{ $fieldKey }}.value"
                                                placeholder="{{ isset($field['placeholder']) ? __($field['placeholder']) : '' }}"
                                                class="flex-1 min-w-0" step="any" />
                                            @break
                                        @case('date')
                                            @if ($currentOperator === 'between')
                                                <div class="flex gap-2 flex-1 min-w-0">
                                                    <flux:input type="date" wire:model="filters.{{ $fieldKey }}.value.0"
                                                        placeholder="{{ __('basics13::messages.date_from') }}"
                                                        class="flex-1 min-w-0" />
                                                    <flux:input type="date" wire:model="filters.{{ $fieldKey }}.value.1"
                                                        placeholder="{{ __('basics13::messages.date_to') }}"
                                                        class="flex-1 min-w-0" />
                                                </div>
                                            @else
                                                <flux:input type="date" wire:model="filters.{{ $fieldKey }}.value"
                                                    placeholder="{{ isset($field['placeholder']) ? __($field['placeholder']) : '' }}"
                                                    class="flex-1 min-w-0" />
                                            @endif
                                            @break
                                        @case('boolean')
                                            <flux:select wire:model="filters.{{ $fieldKey }}.value"
                                                class="flex-1 min-w-0" :aria-label="__('basics13::messages.boolean_value')">
                                                <option value="1">{{ __('basics13::messages.true') }}</option>
                                                <option value="0">{{ __('basics13::messages.false') }}</option>
                                            </flux:select>
                                            @break
                                        @case('select')
                                            @if ($isMultiSelect)
                                                <flux:select wire:model="filters.{{ $fieldKey }}.value[]" multiple
                                                    class="flex-1 min-w-0" :aria-label="__('basics13::messages.select_multiple')">
                                                    @foreach ($selectOptions as $optValue => $optLabel)
                                                        <option value="{{ $optValue }}">{{ $optLabel }}</option>
                                                    @endforeach
                                                </flux:select>
                                            @else
                                                <flux:select wire:model="filters.{{ $fieldKey }}.value"
                                                    class="flex-1 min-w-0" :aria-label="__('basics13::messages.select_single')">
                                                    <option value="">{{ __('basics13::messages.select_placeholder') }}</option>
                                                    @foreach ($selectOptions as $optValue => $optLabel)
                                                        <option value="{{ $optValue }}">{{ $optLabel }}</option>
                                                    @endforeach
                                                </flux:select>
                                            @endif
                                            @break
                                    @endswitch
                                </div>

                                {{-- Help text --}}
                                @isset($field['help'])
                                    <span data-flux-description>{{ __($field['help']) }}</span>
                                @endisset
                            </flux:field>
                        </div>
                    @endforeach
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