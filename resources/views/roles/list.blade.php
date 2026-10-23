@php
    $editingRole = $list['create'] && str_starts_with(old('_role_form', ''), 'edit-');
    $deletedRoleConflict = session('deleted_role_conflict');
    $initialForm = $list['create']
        ? [
            'id' => $editingRole ? old('_role_id') : null,
            'name' => old('name', ''),
            'slug' => old('slug', ''),
            'slugTouched' => $editingRole || old('slug', '') !== '',
            'is_system' => false,
            'description' => old('description', ''),
            'notes' => old('notes', ''),
            'permission_ids' => old('permission_ids', []),
            'context' => $editingRole ? old('_role_form') : 'create',
            'method' => $editingRole ? 'PUT' : 'POST',
            'action' => $editingRole ? route('roles.update', old('_role_id')) : route('roles.store'),
            'title' => $editingRole ? __('Edit role') : __('New role'),
            'submitLabel' => $editingRole ? __('Update role') : __('Save role'),
        ]
        : null;
    $scope = $scope ?? 'active';
@endphp

<x-layouts::app :title="$list['resource']">
    <div x-data="listConfirmation({
        form: @js($initialForm),
        storeUrl: @js(route('roles.store')),
        updateUrl: @js(route('roles.update', '__ROLE__')),
        createRole() {
            this.form = {
                id: null,
                name: '',
                slug: '',
                slugTouched: false,
                is_system: false,
                description: '',
                notes: '',
                permission_ids: [],
                context: 'create',
                method: 'POST',
                action: this.storeUrl,
                title: @js(__('New role')),
                submitLabel: @js(__('Save role')),
            };
        },
        editRole(role) {
            this.form = {
                ...role,
                slugTouched: true,
                context: `edit-${role.id}`,
                method: 'PUT',
                action: this.updateUrl.replace('__ROLE__', role.id),
                title: @js(__('Edit role')),
                submitLabel: @js(__('Update role')),
            };
        },
        {{-- The slug follows the name until somebody types in it; an existing role keeps its own. --}}
        syncSlug() {
            if (this.form.slugTouched || this.form.is_system) {
                return;
            }

            this.form.slug = this.form.name
                .normalize('NFD')
                .replace(/[̀-ͯ]/g, '')
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
        },
    })"
        @if ($errors->any()) x-init="$nextTick(() => $dispatch('modal-show', { name: 'role-form' }))"
        @elseif ($deletedRoleConflict)
            x-init="$nextTick(() => $dispatch('modal-show', { name: 'role-name-conflict' }))"
        @elseif ($list['create'] && request()->boolean('create'))
            x-init="createRole(); $nextTick(() => $dispatch('modal-show', { name: 'role-form' }))" @endif
        class="flex flex-col gap-y-2 sm:gap-y-3">
        <x-basics13::list.page-header :list="$list" prefix="role" :description="__('Sets of permissions given to user accounts.')">
            <x-slot:actions>
                @if ($list['create'])
                    @can('create', \Basics13\Models\Role::class)
                        <x-basics13::list.create-action prefix="role" :label="__('New role')" click="createRole()" />
                    @endcan
                @endif
            </x-slot:actions>
        </x-basics13::list.page-header>

        <x-basics13::list.flash prefix="role" />

        @fragment('list-results')
            <x-basics13::list.searchable-results :search="$list['search']" :total="$roles->total()" :scope="$scope">
                <x-basics13::list.table prefix="role" :paginator="$roles">
                    <x-slot:toolbar>
                        <x-basics13::list.search :search="$list['search']" prefix="role" :scope="$scope" />
                    </x-slot:toolbar>

                    <flux:table.columns>
                        <flux:table.column scope="col">{{ __('Name') }}</flux:table.column>
                        <flux:table.column scope="col">{{ __('Slug') }}</flux:table.column>
                        <flux:table.column scope="col">{{ __('Permissions') }}</flux:table.column>
                        <flux:table.column scope="col">{{ __('Users') }}</flux:table.column>
                        <flux:table.column scope="col" align="end" sticky class="text-end">
                            <span class="sr-only">{{ __('Actions') }}</span>
                        </flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($list['rows'] as $row)
                            <flux:table.row :key="$row['id']"
                                class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <flux:table.cell class="max-w-[18rem]">
                                    <span class="flex min-w-0 items-center gap-2">
                                        <span class="block truncate font-medium" title="{{ $row['name'] }}"
                                            data-test="role-name-{{ $row['id'] }}">{{ $row['name'] }}</span>
                                        @if ($row['isSystem'])
                                            <flux:badge size="sm" color="amber" data-test="role-system-{{ $row['id'] }}">
                                                {{ __('System') }}
                                            </flux:badge>
                                        @endif
                                    </span>
                                    @if ($row['description'])
                                        <span class="block truncate text-sm text-zinc-600 dark:text-zinc-400"
                                            title="{{ $row['description'] }}">{{ $row['description'] }}</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <code class="text-sm text-zinc-600 dark:text-zinc-300"
                                        data-test="role-slug-{{ $row['id'] }}">{{ $row['slug'] }}</code>
                                </flux:table.cell>
                                <flux:table.cell data-test="role-permissions-count-{{ $row['id'] }}">
                                    {{ $row['permissionsCount'] }}
                                </flux:table.cell>
                                <flux:table.cell data-test="role-users-count-{{ $row['id'] }}">
                                    {{ $row['usersCount'] }}
                                </flux:table.cell>
                                <flux:table.cell align="end" sticky class="bg-white dark:bg-zinc-900">
                                    <x-basics13::list.row-actions :actions="$row['actions']" prefix="role"
                                        edit-handler="editRole" />
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <x-basics13::list.empty-state :narrowed="$list['narrowed']" :colspan="5" :message="$list['emptyMessage']">
                                @if ($list['create'] && ! $list['narrowed'])
                                    @can('create', \Basics13\Models\Role::class)
                                        <flux:modal.trigger name="role-form">
                                            <flux:button size="sm" variant="primary" icon="plus" x-on:click="createRole()"
                                                data-test="role-empty-create">
                                                {{ __('New role') }}
                                            </flux:button>
                                        </flux:modal.trigger>
                                    @endcan
                                @endif
                            </x-basics13::list.empty-state>
                        @endforelse
                    </flux:table.rows>
                </x-basics13::list.table>
            </x-basics13::list.searchable-results>
        @endfragment

        @if ($list['create'])
            <flux:modal name="role-form" aria-labelledby="role-form-heading" flyout variant="floating"
                position="right"
                x-on:close="window.clearForm($el.querySelector('form')); window.resetTrackedForms($el)"
                x-on:cancel.prevent="window.requestTrackedModalClose($el)"
                class="form-drawer max-w-none">
                @include('basics13::roles.form')
            </flux:modal>

            @if ($deletedRoleConflict)
                <x-basics13::name-conflict-modal name="role-name-conflict" :title="__('Record Name already in trash')"
                    :message="__('A deleted record already uses the name :name.', ['name' => $deletedRoleConflict['name']])"
                    :create-action="route('roles.store')"
                    :restore-action="route('roles.trash.restore', $deletedRoleConflict['id'])"
                    :create-fields="[
                        'name' => old('name', $deletedRoleConflict['name']),
                        'slug' => old('slug', ''),
                        'description' => old('description', ''),
                    ]"
                    :create-label="__('Create a new role')" :restore-label="__('Restore the deleted record instead')"
                    create-test="role-conflict-create-new" restore-test="role-conflict-restore" />
            @endif
        @endif

        <x-basics13::list.confirm-modal prefix="role" />
    </div>
</x-layouts::app>
