@php
    $editingUserType = $list['create'] && str_starts_with(old('_user_type_form', ''), 'edit-');
    $deletedUserTypeConflict = session('deleted_user_type_conflict');

    // The "create new" button of the conflict modal posts the four names back, so a type can be
    // created with the same euskera name once the user decides not to restore the trashed one.
    $conflictCreateFields = [];

    foreach (['name_eu', 'name_es', 'name_fr', 'name_en'] as $conflictField) {
        $conflictCreateFields[$conflictField] = old(
            $conflictField,
            $deletedUserTypeConflict[$conflictField] ?? '',
        );
    }

    $initialForm = $list['create']
        ? [
            'id' => $editingUserType ? old('_user_type_id') : null,
            'name_eu' => old('name_eu', ''),
            'name_es' => old('name_es', ''),
            'name_fr' => old('name_fr', ''),
            'name_en' => old('name_en', ''),
            'notes' => old('notes', ''),
            'context' => $editingUserType ? old('_user_type_form') : 'create',
            'method' => $editingUserType ? 'PUT' : 'POST',
            'action' => $editingUserType
                ? route('user-types.update', old('_user_type_id'))
                : route('user-types.store'),
            'title' => $editingUserType ? __('Edit user type') : __('New user type'),
            'submitLabel' => $editingUserType ? __('Update user type') : __('Save user type'),
        ]
        : null;
    $scope = $scope ?? 'active';
@endphp

<x-layouts::app :title="$list['resource']">
    <div x-data="listConfirmation({
        form: @js($initialForm),
        storeUrl: @js(route('user-types.store')),
        updateUrl: @js(route('user-types.update', '__ENTITY_TYPE__')),
        createUserType() {
            this.form = {
                id: null,
                name_eu: '',
                name_es: '',
                name_fr: '',
                name_en: '',
                notes: '',
                context: 'create',
                method: 'POST',
                action: this.storeUrl,
                title: @js(__('New user type')),
                submitLabel: @js(__('Save user type')),
            };
        },
        editUserType(userType) {
            this.form = {
                ...userType,
                context: `edit-${userType.id}`,
                method: 'PUT',
                action: this.updateUrl.replace('__ENTITY_TYPE__', userType.id),
                title: @js(__('Edit user type')),
                submitLabel: @js(__('Update user type')),
            };
        },
    })"
        @if ($errors->any()) x-init="$nextTick(() => $dispatch('modal-show', { name: 'user-type-form' }))"
        @elseif ($deletedUserTypeConflict)
            x-init="$nextTick(() => $dispatch('modal-show', { name: 'user-type-name-conflict' }))"
        @elseif ($list['create'] && request()->boolean('create'))
            x-init="createUserType(); $nextTick(() => $dispatch('modal-show', { name: 'user-type-form' }))" @endif
        class="flex flex-col gap-y-2 sm:gap-y-3">
        <x-basics13::list.page-header :list="$list" prefix="user-type" :description="__('Types used to classify accounts.')">
            <x-slot:actions>
                @if ($list['create'])
                    <x-basics13::list.create-action prefix="user-type" :label="__('New user type')"
                        click="createUserType()" />
                @endif
            </x-slot:actions>
        </x-basics13::list.page-header>

        <x-basics13::list.flash prefix="user-type" />

        @fragment('list-results')
            <x-basics13::list.searchable-results :search="$list['search']" :total="$userTypes->total()" :scope="$scope">
                <x-basics13::list.table prefix="user-type" :paginator="$userTypes">
                    <x-slot:toolbar>
                        <x-basics13::list.search :search="$list['search']" prefix="user-type" :scope="$scope" />
                    </x-slot:toolbar>

                    <flux:table.columns>
                        <flux:table.column scope="col">{{ __('Name (eu)') }}</flux:table.column>
                        <flux:table.column scope="col">{{ __('Name (es)') }}</flux:table.column>
                        <flux:table.column scope="col">{{ __('Name (fr)') }}</flux:table.column>
                        <flux:table.column scope="col">{{ __('Name (en)') }}</flux:table.column>
                        <flux:table.column scope="col" align="end" sticky class="text-end">
                            <span class="sr-only">{{ __('Actions') }}</span>
                        </flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($list['rows'] as $row)
                            <flux:table.row :key="$row['id']"
                                class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <flux:table.cell class="max-w-[16rem]">
                                    <span class="block max-w-full truncate" title="{{ $row['nameEu'] }}"
                                        data-test="user-type-name-{{ $row['id'] }}">
                                        {{ $row['nameEu'] }}
                                    </span>
                                </flux:table.cell>
                                <flux:table.cell class="max-w-[16rem]">
                                    {{ $row['nameEs'] ?? '—' }}
                                </flux:table.cell>
                                <flux:table.cell class="max-w-[16rem]">
                                    {{ $row['nameFr'] ?? '—' }}
                                </flux:table.cell>
                                <flux:table.cell class="max-w-[16rem]">
                                    {{ $row['nameEn'] ?? '—' }}
                                </flux:table.cell>
                                <flux:table.cell align="end" sticky
                                    class="bg-white dark:bg-zinc-900">
                                    <x-basics13::list.row-actions :actions="$row['actions']" prefix="user-type"
                                        edit-handler="editUserType" />
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <x-basics13::list.empty-state :narrowed="$list['narrowed']" :colspan="5" :message="$list['emptyMessage']">
                                @if ($list['create'] && ! $list['narrowed'])
                                    <flux:modal.trigger name="user-type-form">
                                        <flux:button size="sm" variant="primary" icon="plus"
                                            x-on:click="createUserType()"
                                            data-test="user-type-empty-create">
                                            {{ __('New user type') }}
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
            <flux:modal name="user-type-form" aria-labelledby="user-type-form-heading"
                flyout variant="floating" position="right"
                x-on:close="window.clearForm($el.querySelector('form')); window.resetTrackedForms($el)"
                x-on:cancel.prevent="window.requestTrackedModalClose($el)"
                class="form-drawer max-w-none">
                @include('basics13::user-types.form')
            </flux:modal>

            @if ($deletedUserTypeConflict)
                <x-basics13::name-conflict-modal name="user-type-name-conflict" :title="__('Record Name already in trash')"
                    :message="__('A deleted record already uses the name :name.', [
                        'name' => $deletedUserTypeConflict['name_eu'],
                    ])" :create-action="route('user-types.store')" :restore-action="route(
                        'user-types.trash.restore',
                        $deletedUserTypeConflict['id'],
                    )" :create-fields="$conflictCreateFields"
                    :create-label="__('Create a new user type')" :restore-label="__('Restore the deleted record instead')"
                    create-test="user-type-conflict-create-new"
                    restore-test="user-type-conflict-restore" />
            @endif
        @endif

        <x-basics13::list.confirm-modal prefix="user-type" />
    </div>
</x-layouts::app>