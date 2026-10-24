@php
    $request = \Basics13\Http\Requests\EmailConfigurationRequest::class;
    $textFields = [
        'name' => ['label' => __('Name'), 'type' => 'text', 'required' => true],
        'sender_name' => ['label' => __('Sender name'), 'type' => 'text', 'required' => true],
        'sender_email' => ['label' => __('Sender email'), 'type' => 'email', 'required' => true],
        'host' => ['label' => __('Host'), 'type' => 'text', 'required' => true],
        'username' => ['label' => __('Username'), 'type' => 'text', 'required' => false],
    ];
@endphp

<x-basics13::forms.tracked-resource prefix="email-configuration" context-field="_email_configuration_form"
    id-field="_email_configuration_id">
    @foreach ($textFields as $field => $definition)
        <flux:field>
            <x-basics13::forms.field-label :request="$request" :field="$field" :test="'email-configuration-' . $field . '-info'"
                :for="'email-configuration-' . $field">
                {{ $definition['label'] }}
            </x-basics13::forms.field-label>
            <flux:input name="{{ $field }}" type="{{ $definition['type'] }}" x-model="form.{{ $field }}"
                :required="$definition['required']" :autofocus="$field === 'name'"
                maxlength="{{ \Basics13\Support\Validation\MaxLength::string() }}"
                id="email-configuration-{{ $field }}" data-test="email-configuration-{{ $field }}" />
            <flux:error name="{{ $field }}" />
        </flux:field>
    @endforeach

    <flux:field>
        <x-basics13::forms.field-label :request="$request" field="port" test="email-configuration-port-info"
            for="email-configuration-port">
            {{ __('Port') }}
        </x-basics13::forms.field-label>
        <flux:input name="port" type="number" min="1" max="65535" required x-model="form.port"
            id="email-configuration-port" data-test="email-configuration-port" />
        <flux:error name="port" />
    </flux:field>

    <flux:field>
        <x-basics13::forms.field-label :request="$request" field="encryption" test="email-configuration-encryption-info"
            for="email-configuration-encryption">
            {{ __('Encryption') }}
        </x-basics13::forms.field-label>
        <flux:select name="encryption" x-model="form.encryption" id="email-configuration-encryption"
            data-test="email-configuration-encryption">
            <flux:select.option value="tls">{{ __('STARTTLS') }}</flux:select.option>
            <flux:select.option value="ssl">{{ __('SSL/TLS') }}</flux:select.option>
            <flux:select.option value="none">{{ __('None') }}</flux:select.option>
        </flux:select>
        <flux:error name="encryption" />
    </flux:field>

    {{-- The stored password is never sent to the browser: the field always opens empty. --}}
    <flux:field>
        <x-basics13::forms.field-label :request="$request" field="password" test="email-configuration-password-info"
            for="email-configuration-password">
            {{ __('Password') }}
        </x-basics13::forms.field-label>
        <flux:input name="password" type="password" autocomplete="new-password" x-model="form.password"
            maxlength="{{ \Basics13\Support\Validation\MaxLength::string() }}"
            id="email-configuration-password" data-test="email-configuration-password" />
        <flux:description x-show="form.id">{{ __('Leave it empty to keep the current password.') }}</flux:description>
        <flux:error name="password" />
    </flux:field>

    <flux:field variant="inline">
        <input type="hidden" name="default" value="0">
        <flux:checkbox name="default" value="1" x-model="form.default" id="email-configuration-default"
            data-test="email-configuration-default" />
        <flux:label for="email-configuration-default">{{ __('Use as the default configuration') }}</flux:label>
        <flux:error name="default" />
    </flux:field>

    <flux:field>
        <x-basics13::forms.field-label :request="$request" field="notes" test="email-configuration-notes-info"
            for="email-configuration-notes">
            {{ __('Notes') }}
        </x-basics13::forms.field-label>
        <flux:textarea name="notes" x-model="form.notes" rows="3"
            maxlength="{{ \Basics13\Support\Validation\MaxLength::longText() }}"
            id="email-configuration-notes" data-test="email-configuration-notes" />
        <flux:error name="notes" />
    </flux:field>
</x-basics13::forms.tracked-resource>
