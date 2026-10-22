<x-basics13::forms.tracked-resource prefix="user" context-field="_user_form" id-field="_user_id">
    <flux:field>
        <x-basics13::forms.field-label :request="\Basics13\Http\Requests\UserRequest::class" field="name"
            test="user-name-info" for="user-name">
            {{ __('Name') }}
        </x-basics13::forms.field-label>
        <flux:input name="name" x-model="form.name"
            maxlength="{{ \Basics13\Support\Validation\MaxLength::string() }}" required autofocus
            id="user-name" data-test="user-name" />
        <flux:error name="name" />
    </flux:field>

    <flux:field>
        <x-basics13::forms.field-label :request="\Basics13\Http\Requests\UserRequest::class" field="email"
            test="user-email-info" for="user-email">
            {{ __('Email') }}
        </x-basics13::forms.field-label>
        {{-- Only the account holder changes the sign-in email; for anyone else it is shown read-only. --}}
        <flux:input name="email" x-model="form.email" type="email"
            maxlength="{{ \Basics13\Support\Validation\MaxLength::string() }}" required
            x-bind:readonly="form.context !== 'create' && ! form.own"
            id="user-email" data-test="user-email" />
        <flux:text class="text-xs" x-show="form.context !== 'create' && ! form.own" data-test="user-own-account-only">
            {{ __('basics13::messages.own_account_only') }}
        </flux:text>
        <flux:error name="email" />
    </flux:field>

    <flux:field>
        <x-basics13::forms.field-label :request="\Basics13\Http\Requests\UserRequest::class" field="lang"
            test="user-lang-info" for="user-lang">
            {{ __('Language') }}
        </x-basics13::forms.field-label>
        <flux:select id="user-lang" name="lang" x-model="form.lang" data-test="user-lang">
            <option value="">{{ __('Application default') }}</option>
            @foreach (\Basics13\Enums\Language::options() as $code => $nativeName)
                <option value="{{ $code }}" lang="{{ $code }}">{{ $nativeName }}</option>
            @endforeach
        </flux:select>
        <flux:error name="lang" />
    </flux:field>

    {{-- The password, like the email, is changed only by the account holder or set on creation. --}}
    <flux:field x-show="form.context === 'create' || form.own">
        {{--
            The password is required when the account is created and optional when it is edited: the
            label and its asterisk follow the mode the drawer is in, and the notice says what an
            empty field means. The field never carries the current password back.
        --}}
        <flux:label for="user-password" class="gap-1">
            <span x-text="form.context === 'create' ? @js(__('Password')) : @js(__('New password'))"></span>
            <span class="text-red-600" aria-hidden="true" x-show="form.context === 'create'">*</span>
        </flux:label>
        <flux:input name="password" x-model="form.password" type="password"
            autocomplete="new-password" x-bind:required="form.context === 'create'"
            id="user-password" data-test="user-password" />
        <flux:text class="text-xs" x-show="form.context !== 'create'">
            {{ __('Leave empty to keep the current password.') }}
        </flux:text>
        <flux:error name="password" />
    </flux:field>

    <flux:field x-show="form.context === 'create' || form.own">
        <x-basics13::forms.field-label :request="\Basics13\Http\Requests\UserRequest::class"
            field="password_confirmation" test="user-password-confirmation-info"
            for="user-password-confirmation">
            {{ __('Confirm password') }}
        </x-basics13::forms.field-label>
        <flux:input name="password_confirmation" x-model="form.password_confirmation" type="password"
            autocomplete="new-password" id="user-password-confirmation"
            data-test="user-password-confirmation" />
        <flux:error name="password_confirmation" />
    </flux:field>

    <flux:field>
        <x-basics13::forms.field-label :request="\Basics13\Http\Requests\UserRequest::class" field="notes"
            test="user-notes-info" for="user-notes">
            {{ __('Notes') }}
        </x-basics13::forms.field-label>
        <flux:textarea name="notes" x-model="form.notes" rows="3"
            maxlength="{{ \Basics13\Support\Validation\MaxLength::longText() }}"
            id="user-notes" data-test="user-notes" />
        <flux:error name="notes" />
    </flux:field>
</x-basics13::forms.tracked-resource>
