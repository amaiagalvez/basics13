@php
    $page = [
        'resource' => __('Application configuration'),
        'breadcrumbs' => [
            ['label' => __('Application configuration'), 'url' => null],
        ],
        'tabs' => $tabs,
    ];
@endphp

<x-layouts::app :title="$page['resource']">
    <div class="flex flex-col gap-y-4 sm:gap-y-6">
        <x-basics13::list.page-header :list="$page" prefix="configuration"
            :description="__('Values the application reads while it runs. A saved value applies from the next request.')" />

        <x-basics13::list.flash prefix="configuration" />

        <form method="POST" action="{{ route('configuration.update', $package) }}"
            x-data="{ isSubmitting: false, isDirty: false }"
            x-on:form-dirty-change="isDirty = $event.detail.isDirty"
            x-on:submit="if (!isDirty || isSubmitting) { $event.preventDefault(); return; } isSubmitting = true"
            data-track-changes data-required-message="{{ __('validation.required') }}"
            class="flex w-full max-w-2xl flex-col gap-6" data-test="configuration-form">
            @csrf
            @method('PUT')

            @foreach ($settings as $setting)
                @php
                    $field = 'values.'.$setting->variable;
                    $isInteger = $setting->type === \Basics13\Enums\SettingType::Integer;
                @endphp
                @if ($setting->type === \Basics13\Enums\SettingType::Languages)
                    @php($chosen = old($field, explode(',', (string) $setting->value)))
                    <flux:fieldset data-test="configuration-{{ $setting->variable }}">
                        <flux:legend>
                            {{ $setting->label() }}
                            <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span>
                        </flux:legend>
                        <flux:description>{{ $setting->description() }}</flux:description>
                        <div class="flex flex-wrap gap-x-8 gap-y-3">
                            @foreach (\Basics13\Enums\Language::options() as $code => $nativeName)
                                <label class="flex cursor-pointer items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                                    <input type="checkbox" name="values[{{ $setting->variable }}][]" value="{{ $code }}"
                                        @checked(in_array($code, (array) $chosen, true)) @disabled(! $canEdit)
                                        id="setting-{{ $setting->variable }}-{{ $code }}"
                                        data-test="configuration-{{ $setting->variable }}-{{ $code }}"
                                        class="size-4 shrink-0 rounded accent-brand-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus dark:accent-brand-400" />
                                    <span lang="{{ $code }}">{{ $nativeName }}</span>
                                </label>
                            @endforeach
                        </div>
                        <flux:error :name="$field" />
                    </flux:fieldset>
                    @continue
                @endif
                <flux:field>
                    <flux:label for="setting-{{ $setting->variable }}">
                        {{ $setting->label() }}
                        @if ($isInteger)
                            <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span>
                        @endif
                    </flux:label>
                    <flux:description>{{ $setting->description() }}</flux:description>
                    <flux:input id="setting-{{ $setting->variable }}" name="values[{{ $setting->variable }}]"
                        :value="old($field, $setting->value)"
                        :type="match ($setting->type) { \Basics13\Enums\SettingType::Integer => 'number', \Basics13\Enums\SettingType::Email => 'email', default => 'text' }"
                        :min="$isInteger ? \Basics13\Models\Setting::MIN_INTEGER : null"
                        :max="$isInteger ? \Basics13\Models\Setting::MAX_INTEGER : null"
                        :maxlength="$isInteger ? null : config('basics13.validation.max_length.string')"
                        :required="$isInteger" :disabled="! $canEdit"
                        data-test="configuration-{{ $setting->variable }}" />
                    <flux:error :name="$field" />
                </flux:field>
            @endforeach

            @if ($canEdit)
                <div class="flex flex-wrap items-center justify-end gap-3">
                    <flux:text x-show="!isDirty" id="configuration-submit-hint" data-test="configuration-submit-hint"
                        class="me-auto text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Nothing to save yet.') }}
                    </flux:text>
                    <flux:button variant="primary" type="submit" disabled
                        x-bind:disabled="isSubmitting || !isDirty" x-bind:aria-busy="isSubmitting"
                        aria-describedby="configuration-submit-hint" data-test="configuration-submit">
                        {{ __('Save') }}
                    </flux:button>
                </div>
            @endif
        </form>
    </div>
</x-layouts::app>
