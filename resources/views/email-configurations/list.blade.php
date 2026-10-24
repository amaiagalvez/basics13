@php
    $editingConfiguration = $list['create'] && str_starts_with(old('_email_configuration_form', ''), 'edit-');
    $deletedConfigurationConflict = session('deleted_email_configuration_conflict');
    $emptyForm = [
        'name' => '',
        'sender_name' => '',
        'sender_email' => '',
        'host' => '',
        'port' => 587,
        'username' => '',
        'password' => '',
        'encryption' => 'tls',
        'default' => false,
        'notes' => '',
    ];

    $initialForm = $list['create']
        ? [
            ...$emptyForm,
            'id' => $editingConfiguration ? old('_email_configuration_id') : null,
            ...array_intersect_key(old(), $emptyForm),
            // The password never travels back, not even after a failed validation.
            'password' => '',
            'default' => (bool) old('default', false),
            'context' => $editingConfiguration ? old('_email_configuration_form') : 'create',
            'method' => $editingConfiguration ? 'PUT' : 'POST',
            'action' => $editingConfiguration
                ? route('email-configurations.update', old('_email_configuration_id'))
                : route('email-configurations.store'),
            'title' => $editingConfiguration ? __('Edit SMTP configuration') : __('New SMTP configuration'),
            'submitLabel' => $editingConfiguration ? __('Update SMTP configuration') : __('Save SMTP configuration'),
        ]
        : null;
    $scope = $scope ?? 'active';
@endphp

<x-layouts::app :title="$list['resource']">
    <div x-data="listConfirmation({
        form: @js($initialForm),
        emptyForm: @js($emptyForm),
        storeUrl: @js(route('email-configurations.store')),
        updateUrl: @js(route('email-configurations.update', '__RECORD__')),
        sendTest: @js(['action' => old('_send_test_action', ''), 'name' => old('_send_test_name', '')]),
        openSendTest(target) {
            this.sendTest = target;
        },
        createConfiguration() {
            this.form = {
                ...this.emptyForm,
                id: null,
                context: 'create',
                method: 'POST',
                action: this.storeUrl,
                title: @js(__('New SMTP configuration')),
                submitLabel: @js(__('Save SMTP configuration')),
            };
        },
        editConfiguration(configuration) {
            this.form = {
                ...configuration,
                context: `edit-${configuration.id}`,
                method: 'PUT',
                action: this.updateUrl.replace('__RECORD__', configuration.id),
                title: @js(__('Edit SMTP configuration')),
                submitLabel: @js(__('Update SMTP configuration')),
            };
        },
    })"
        @if ($errors->has('recipient')) x-init="$nextTick(() => $dispatch('modal-show', { name: 'email-configuration-send-test' }))"
        @elseif ($errors->any()) x-init="$nextTick(() => $dispatch('modal-show', { name: 'email-configuration-form' }))"
        @elseif ($deletedConfigurationConflict)
            x-init="$nextTick(() => $dispatch('modal-show', { name: 'email-configuration-name-conflict' }))"
        @elseif ($list['create'] && request()->boolean('create'))
            x-init="createConfiguration(); $nextTick(() => $dispatch('modal-show', { name: 'email-configuration-form' }))" @endif
        class="flex flex-col gap-y-2 sm:gap-y-3">
        <x-basics13::list.page-header :list="$list" prefix="email-configuration" :description="__('SMTP servers the application sends email through.')">
            <x-slot:actions>
                @if ($list['create'])
                    <x-basics13::list.create-action prefix="email-configuration" :label="__('New SMTP configuration')"
                        click="createConfiguration()" />
                @endif
            </x-slot:actions>
        </x-basics13::list.page-header>

        <x-basics13::list.flash prefix="email-configuration" />

        @fragment('list-results')
            <x-basics13::list.searchable-results :search="$list['search']" :total="$emailConfigurations->total()" :scope="$scope">
                <x-basics13::list.table prefix="email-configuration" :paginator="$emailConfigurations">
                    <x-slot:toolbar>
                        <x-basics13::list.search :search="$list['search']" prefix="email-configuration" :scope="$scope" />
                    </x-slot:toolbar>

                    <flux:table.columns>
                        <flux:table.column scope="col">{{ __('Name') }}</flux:table.column>
                        <flux:table.column scope="col">{{ __('Server') }}</flux:table.column>
                        <flux:table.column scope="col">{{ __('Sender email') }}</flux:table.column>
                        <flux:table.column scope="col" align="end" sticky class="text-end">
                            <span class="sr-only">{{ __('Actions') }}</span>
                        </flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($list['rows'] as $row)
                            <flux:table.row :key="$row['id']"
                                class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <flux:table.cell class="max-w-[16rem]">
                                    <span class="flex items-center gap-2">
                                        <span class="block max-w-full truncate" title="{{ $row['name'] }}"
                                            data-test="email-configuration-name-{{ $row['id'] }}">{{ $row['name'] }}</span>
                                        @if ($row['isDefault'])
                                            <flux:badge size="sm" color="green" data-test="email-configuration-default-{{ $row['id'] }}">
                                                {{ __('Default') }}
                                            </flux:badge>
                                        @endif
                                    </span>
                                </flux:table.cell>
                                <flux:table.cell>{{ $row['host'] }}:{{ $row['port'] }}</flux:table.cell>
                                <flux:table.cell>{{ $row['senderEmail'] }}</flux:table.cell>
                                <flux:table.cell align="end" sticky class="bg-white dark:bg-zinc-900">
                                    <x-basics13::list.row-actions :actions="$row['actions']" prefix="email-configuration"
                                        edit-handler="editConfiguration" />
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <x-basics13::list.empty-state :narrowed="$list['narrowed']" :colspan="4" :message="$list['emptyMessage']">
                                @if ($list['create'] && ! $list['narrowed'])
                                    <flux:modal.trigger name="email-configuration-form">
                                        <flux:button size="sm" variant="primary" icon="plus"
                                            x-on:click="createConfiguration()"
                                            data-test="email-configuration-empty-create">
                                            {{ __('New SMTP configuration') }}
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
            <flux:modal name="email-configuration-form" aria-labelledby="email-configuration-form-heading"
                flyout variant="floating" position="right"
                x-on:close="window.clearForm($el.querySelector('form')); window.resetTrackedForms($el)"
                x-on:cancel.prevent="window.requestTrackedModalClose($el)"
                class="form-drawer max-w-none">
                @include('basics13::email-configurations.form')
            </flux:modal>

            <x-basics13::list.send-test-modal prefix="email-configuration" />

            @if ($deletedConfigurationConflict)
                <x-basics13::name-conflict-modal name="email-configuration-name-conflict" :title="__('Record Name already in trash')"
                    :message="__('A deleted record already uses the name :name.', ['name' => $deletedConfigurationConflict['name']])"
                    :create-action="route('email-configurations.store')"
                    :restore-action="route('email-configurations.trash.restore', $deletedConfigurationConflict['id'])"
                    :create-fields="array_intersect_key(old(), $emptyForm)"
                    :create-label="__('Create a new SMTP configuration')" :restore-label="__('Restore the deleted record instead')"
                    create-test="email-configuration-conflict-create-new"
                    restore-test="email-configuration-conflict-restore" />
            @endif
        @endif

        <x-basics13::list.confirm-modal prefix="email-configuration" />
    </div>
</x-layouts::app>
