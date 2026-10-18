@php
    $editingUser = $list['create'] && str_starts_with(old('_user_form', ''), 'edit-');
    $initialForm = $list['create']
        ? [
            'id' => $editingUser ? old('_user_id') : null,
            'name' => old('name', ''),
            'email' => old('email', ''),
            'notes' => old('notes', ''),
            'password' => '',
            'password_confirmation' => '',
            'context' => $editingUser ? old('_user_form') : 'create',
            'method' => $editingUser ? 'PUT' : 'POST',
            'action' => $editingUser
                ? route('users.update', old('_user_id'))
                : route('users.store'),
            'title' => $editingUser ? __('Edit user') : __('New user'),
            'submitLabel' => $editingUser ? __('Update user') : __('Save user'),
        ]
        : null;
@endphp

<x-layouts::app :title="$list['resource']">
    <div x-data="listConfirmation({
        form: @js($initialForm),
        storeUrl: @js(route('users.store')),
        updateUrl: @js(route('users.update', '__USER__')),
        createUser() {
            this.form = {
                id: null,
                name: '',
                email: '',
                notes: '',
                password: '',
                password_confirmation: '',
                context: 'create',
                method: 'POST',
                action: this.storeUrl,
                title: @js(__('New user')),
                submitLabel: @js(__('Save user')),
            };
        },
        editUser(user) {
            this.form = {
                ...user,
                password: '',
                password_confirmation: '',
                context: `edit-${user.id}`,
                method: 'PUT',
                action: this.updateUrl.replace('__USER__', user.id),
                title: @js(__('Edit user')),
                submitLabel: @js(__('Update user')),
            };
        },
    })"
        @if ($errors->any()) x-init="$nextTick(() => $dispatch('modal-show', { name: 'user-form' }))"
        @elseif ($list['create'] && request()->boolean('create'))
            x-init="createUser(); $nextTick(() => $dispatch('modal-show', { name: 'user-form' }))" @endif
        class="flex flex-col gap-y-2 sm:gap-y-3">
        <x-basics13::list.page-header :list="$list" prefix="user">
            <x-slot:actions>
                @if ($list['create'])
                    <x-basics13::list.create-action prefix="user" :label="__('New user')"
                        click="createUser()" />
                @endif
            </x-slot:actions>
        </x-basics13::list.page-header>

        <x-basics13::list.flash prefix="user" />

        @fragment('list-results')
            <x-basics13::list.searchable-results :search="$list['search']" :total="$users->total()">
                <x-basics13::list.search :search="$list['search']" prefix="user" />

                <x-basics13::list.table prefix="user" :paginator="$users">
                    <flux:table.columns>
                        <flux:table.column scope="col">{{ __('Name') }}</flux:table.column>
                        <flux:table.column scope="col">{{ __('Email') }}</flux:table.column>
                        <flux:table.column scope="col" align="end" sticky class="text-end">
                            <span class="sr-only">{{ __('Actions') }}</span>
                        </flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($list['rows'] as $row)
                            <flux:table.row :key="$row['id']"
                                class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <flux:table.cell class="max-w-[16rem]">
                                    <flux:modal.trigger name="user-form">
                                        <button type="button"
                                            class="block max-w-full truncate text-start font-medium text-zinc-900 hover:underline dark:text-zinc-100"
                                            x-on:click="editUser(JSON.parse($el.dataset.payload))"
                                            data-payload="{{ json_encode($row['editPayload']) }}"
                                            title="{{ $row['name'] }}"
                                            data-test="user-name-{{ $row['id'] }}">{{ $row['name'] }}</button>
                                    </flux:modal.trigger>
                                </flux:table.cell>
                                <flux:table.cell class="max-w-[16rem]">
                                    <span class="block max-w-full truncate text-zinc-600 dark:text-zinc-300"
                                        title="{{ $row['email'] }}"
                                        data-test="user-email-{{ $row['id'] }}">{{ $row['email'] }}</span>
                                </flux:table.cell>
                                <flux:table.cell align="end" sticky
                                    class="bg-white dark:bg-zinc-900">
                                    <x-basics13::list.row-actions :actions="$row['actions']" prefix="user"
                                        edit-handler="editUser" />
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <x-basics13::list.empty-state :colspan="3" :message="$list['emptyMessage']">
                                @if ($list['create'] && $list['search']['value'] === '')
                                    <flux:modal.trigger name="user-form">
                                        <flux:button size="sm" variant="primary" icon="plus"
                                            x-on:click="createUser()"
                                            data-test="user-empty-create">
                                            {{ __('New user') }}
                                        </flux:button>
                                    </flux:modal.trigger>
                                @endif
                            </x-basics13::list.empty-state>
                        @endforelse
                    </flux:table.rows>
                </x-basics13::list.table>
            </x-basics13::list.searchable-results>
        @endfragment

        @if ($list['create'])
            <flux:modal name="user-form" aria-labelledby="user-form-heading"
                variant="flyout" position="right"
                x-on:close="window.clearForm($el.querySelector('form')); window.resetTrackedForms($el)"
                x-on:cancel.prevent="window.requestTrackedModalClose($el)"
                class="form-drawer max-w-none">
                @include('basics13::users.form')
            </flux:modal>
        @endif

        <x-basics13::list.confirm-modal prefix="user" />
    </div>
</x-layouts::app>
