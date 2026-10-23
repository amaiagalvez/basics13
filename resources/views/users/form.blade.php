@php
    // The selector is only rendered from the active list, and only for a user who may assign roles;
    // every other list passes nothing.
    $assignableRoles = $assignableRoles ?? null;
    $assignableTypes = $assignableTypes ?? null;
    $assignsTypeAdmins = $assignsTypeAdmins ?? false;
@endphp

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

    @if ($assignableRoles !== null)
        {{--
            Nobody changes their own roles, so the selector hides on the own account and its fields
            are disabled: a disabled control is not posted, and the request then leaves the roles as
            they are. `assign_roles` tells an emptied selection apart from a form without selector.
        --}}
        <flux:field x-show="! form.own">
            <input type="hidden" name="assign_roles" value="1" x-bind:disabled="form.own" />
            <x-basics13::forms.field-label :request="\Basics13\Http\Requests\UserRequest::class" field="role_ids"
                test="user-role-ids-info" for="user-role-ids" :notices="[__('You can pick several roles.')]">
                {{ __('Roles') }}
            </x-basics13::forms.field-label>

            <div id="user-role-ids" data-test="user-role-ids" role="group" aria-label="{{ __('Roles') }}"
                class="max-h-64 divide-y divide-zinc-100 overflow-y-auto rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white shadow-xs dark:divide-white/5 dark:border-white/10 dark:bg-white/10">
                @forelse ($assignableRoles as $role)
                    <label data-role-active="{{ $role->active ? 'true' : 'false' }}"
                        x-show="$el.dataset.roleActive === 'true' || (form.role_ids ?? []).includes($el.querySelector('input').value)"
                        class="flex cursor-pointer items-center gap-3 px-3 py-2.5 text-sm text-zinc-700 transition-colors hover:bg-zinc-50 has-[:checked]:bg-brand-50 dark:text-zinc-300 dark:hover:bg-white/5 dark:has-[:checked]:bg-brand-500/10">
                        <input type="checkbox" name="role_ids[]" value="{{ (string) $role->id }}"
                            x-model="form.role_ids" x-bind:disabled="form.own"
                            data-test="user-role-{{ $role->id }}"
                            class="size-4 shrink-0 rounded accent-brand-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus dark:accent-brand-400" />
                        <span class="min-w-0 flex-1 truncate">{{ $role->name }}</span>
                        @if ($role->is_system)
                            <flux:badge size="sm" color="amber">{{ __('System') }}</flux:badge>
                        @endif
                        @unless ($role->active)
                            <span
                                class="shrink-0 rounded-full bg-zinc-800/5 px-2 py-0.5 text-xs text-zinc-600 dark:bg-white/10 dark:text-zinc-300">
                                {{ __('basics13::messages.archived_label') }}
                            </span>
                        @endunless
                    </label>
                @empty
                    <p class="px-3 py-2.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No roles yet.') }}</p>
                @endforelse
            </div>

            <flux:error name="role_ids" />
            <flux:error name="role_ids.*" />
        </flux:field>
    @endif

    @if ($assignableTypes !== null)
        {{--
            `assign_types` tells an emptied selection apart from a form without selector. The admin
            flag of each type is only set by whoever edits accounts globally; a type admin is offered
            only the types it administers.
        --}}
        <flux:field>
            <input type="hidden" name="assign_types" value="1" />
            <x-basics13::forms.field-label :request="\Basics13\Http\Requests\UserRequest::class" field="user_type_ids"
                test="user-type-ids-info" for="user-type-ids" :notices="[__('You can pick several types.')]">
                {{ __('User types') }}
            </x-basics13::forms.field-label>

            <div id="user-type-ids" data-test="user-type-ids" role="group" aria-label="{{ __('User types') }}"
                class="max-h-64 divide-y divide-zinc-100 overflow-y-auto rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white shadow-xs dark:divide-white/5 dark:border-white/10 dark:bg-white/10">
                @forelse ($assignableTypes as $type)
                    <div data-type-active="{{ $type->active ? 'true' : 'false' }}"
                        x-show="$el.dataset.typeActive === 'true' || (form.user_type_ids ?? []).includes('{{ (string) $type->id }}')"
                        class="flex items-center gap-3 px-3 py-2.5 text-sm text-zinc-700 has-[:checked]:bg-brand-50 dark:text-zinc-300 dark:has-[:checked]:bg-brand-500/10">
                        <label class="flex min-w-0 flex-1 cursor-pointer items-center gap-3">
                            <input type="checkbox" name="user_type_ids[]" value="{{ (string) $type->id }}"
                                x-model="form.user_type_ids" data-test="user-type-{{ $type->id }}"
                                class="size-4 shrink-0 rounded accent-brand-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus dark:accent-brand-400" />
                            <span class="min-w-0 flex-1 truncate">{{ $type->name_eu }}</span>
                        </label>
                        @unless ($type->active)
                            <span
                                class="shrink-0 rounded-full bg-zinc-800/5 px-2 py-0.5 text-xs text-zinc-600 dark:bg-white/10 dark:text-zinc-300">
                                {{ __('basics13::messages.archived_label') }}
                            </span>
                        @endunless
                        @if ($assignsTypeAdmins)
                            <label class="flex shrink-0 cursor-pointer items-center gap-2 text-xs">
                                <input type="checkbox" name="admin_type_ids[]" value="{{ (string) $type->id }}"
                                    x-model="form.admin_type_ids"
                                    x-bind:disabled="! (form.user_type_ids ?? []).includes('{{ (string) $type->id }}')"
                                    data-test="user-type-admin-{{ $type->id }}"
                                    class="size-4 rounded accent-brand-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus dark:accent-brand-400" />
                                {{ __('Type admin') }}
                            </label>
                        @endif
                    </div>
                @empty
                    <p class="px-3 py-2.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No user types yet.') }}</p>
                @endforelse
            </div>

            <flux:error name="user_type_ids" />
            <flux:error name="user_type_ids.*" />
            <flux:error name="admin_type_ids.*" />
        </flux:field>
    @endif

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
