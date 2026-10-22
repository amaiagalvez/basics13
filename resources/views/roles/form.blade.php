@php
    // The form is only rendered from the active list, which is the only state that passes the
    // permissions in.
    $permissionsByModule = $permissionsByModule ?? collect();
@endphp

<x-basics13::forms.tracked-resource prefix="role" context-field="_role_form" id-field="_role_id">
    <flux:field>
        <x-basics13::forms.field-label :request="\Basics13\Http\Requests\RoleRequest::class" field="name"
            test="role-name-info" for="role-name">
            {{ __('Name') }}
        </x-basics13::forms.field-label>
        <flux:input name="name" x-model="form.name" x-on:input="syncSlug()" minlength="2"
            maxlength="{{ \Basics13\Support\Validation\MaxLength::string() }}" required autofocus
            id="role-name" data-test="role-name" />
        <flux:error name="name" />
    </flux:field>

    <flux:field>
        <x-basics13::forms.field-label :request="\Basics13\Http\Requests\RoleRequest::class" field="slug"
            test="role-slug-info" for="role-slug"
            :notices="[__('Filled in from the name until you change it.')]">
            {{ __('Slug') }}
        </x-basics13::forms.field-label>
        {{-- The policies and the data migrations find a system role by its slug, so it stays as it is. --}}
        <flux:input name="slug" x-model="form.slug" x-on:input="form.slugTouched = true"
            x-bind:readonly="form.is_system"
            maxlength="{{ \Basics13\Support\Validation\MaxLength::string() }}" required
            pattern="[a-z0-9]+(-[a-z0-9]+)*" id="role-slug" data-test="role-slug" />
        <flux:text class="text-xs" x-show="form.is_system" data-test="role-slug-system">
            {{ __('The slug of a system role cannot change.') }}
        </flux:text>
        <flux:error name="slug" />
    </flux:field>

    <flux:field>
        <x-basics13::forms.field-label :request="\Basics13\Http\Requests\RoleRequest::class" field="description"
            test="role-description-info" for="role-description">
            {{ __('Description') }}
        </x-basics13::forms.field-label>
        <flux:input name="description" x-model="form.description"
            maxlength="{{ \Basics13\Support\Validation\MaxLength::string() }}"
            id="role-description" data-test="role-description" />
        <flux:error name="description" />
    </flux:field>

    <flux:field>
        <x-basics13::forms.field-label :request="\Basics13\Http\Requests\RoleRequest::class" field="permission_ids"
            test="role-permission-ids-info" for="role-permission-ids"
            :notices="[__('Full access grants every action of its module.')]">
            {{ __('Permissions') }}
        </x-basics13::forms.field-label>

        {{-- One group of checkboxes per module, in the order the permissions are seeded. --}}
        <div id="role-permission-ids" data-test="role-permission-ids" class="flex flex-col gap-3">
            @forelse ($permissionsByModule as $module => $permissions)
                <fieldset data-test="role-permission-module-{{ $module }}"
                    class="rounded-lg border border-zinc-200 bg-white shadow-xs dark:border-white/10 dark:bg-white/10">
                    <legend class="sr-only">{{ \Basics13\Models\Permission::moduleLabel($module) }}</legend>
                    <p aria-hidden="true"
                        class="border-b border-zinc-100 px-3 py-2 text-sm font-medium text-zinc-800 dark:border-white/5 dark:text-zinc-100">
                        {{ \Basics13\Models\Permission::moduleLabel($module) }}
                    </p>
                    <div class="grid grid-cols-1 gap-x-3 sm:grid-cols-2">
                        @foreach ($permissions as $permission)
                            <label
                                class="flex cursor-pointer items-center gap-3 px-3 py-2 text-sm text-zinc-700 transition-colors hover:bg-zinc-50 has-[:checked]:bg-brand-50 dark:text-zinc-300 dark:hover:bg-white/5 dark:has-[:checked]:bg-brand-500/10">
                                <input type="checkbox" name="permission_ids[]" value="{{ (string) $permission->id }}"
                                    x-model="form.permission_ids" data-test="role-permission-{{ $permission->slug }}"
                                    class="size-4 shrink-0 rounded accent-brand-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus dark:accent-brand-400" />
                                <span class="min-w-0 flex-1">{{ $permission->label() }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @empty
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No permissions yet.') }}</p>
            @endforelse
        </div>

        <flux:error name="permission_ids" />
        <flux:error name="permission_ids.*" />
    </flux:field>

    <flux:field>
        <x-basics13::forms.field-label :request="\Basics13\Http\Requests\RoleRequest::class" field="notes"
            test="role-notes-info" for="role-notes">
            {{ __('Notes') }}
        </x-basics13::forms.field-label>
        <flux:textarea name="notes" x-model="form.notes" rows="3"
            maxlength="{{ \Basics13\Support\Validation\MaxLength::longText() }}" id="role-notes"
            data-test="role-notes" />
        <flux:error name="notes" />
    </flux:field>
</x-basics13::forms.tracked-resource>
