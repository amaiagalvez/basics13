@php
    $editingTemplate = $list['create'] && str_starts_with(old('_email_template_form', ''), 'edit-');
    $deletedTemplateConflict = session('deleted_email_template_conflict');
    $emptyForm = [
        'name' => '',
        'banner' => '',
        'legal_text' => '',
        'subject_eu' => '',
        'subject_es' => '',
        'subject_fr' => '',
        'subject_en' => '',
        'body_eu' => '',
        'body_es' => '',
        'body_fr' => '',
        'body_en' => '',
        'default' => false,
        'notes' => '',
    ];

    $initialForm = $list['create']
        ? [
            ...$emptyForm,
            'id' => $editingTemplate ? old('_email_template_id') : null,
            ...array_intersect_key(old(), $emptyForm),
            'default' => (bool) old('default', false),
            'context' => $editingTemplate ? old('_email_template_form') : 'create',
            'method' => $editingTemplate ? 'PUT' : 'POST',
            'action' => $editingTemplate
                ? route('email-templates.update', old('_email_template_id'))
                : route('email-templates.store'),
            'title' => $editingTemplate ? __('Edit email template') : __('New email template'),
            'submitLabel' => $editingTemplate ? __('Update email template') : __('Save email template'),
        ]
        : null;
    $scope = $scope ?? 'active';
@endphp

<x-layouts::app :title="$list['resource']">
    <div x-data="listConfirmation({
        form: @js($initialForm),
        emptyForm: @js($emptyForm),
        storeUrl: @js(route('email-templates.store')),
        updateUrl: @js(route('email-templates.update', '__RECORD__')),
        sendTest: @js(['action' => old('_send_test_action', ''), 'name' => old('_send_test_name', '')]),
        openSendTest(target) {
            this.sendTest = target;
        },
        createTemplate() {
            this.form = {
                ...this.emptyForm,
                id: null,
                context: 'create',
                method: 'POST',
                action: this.storeUrl,
                title: @js(__('New email template')),
                submitLabel: @js(__('Save email template')),
            };
        },
        editTemplate(template) {
            this.form = {
                ...template,
                context: `edit-${template.id}`,
                method: 'PUT',
                action: this.updateUrl.replace('__RECORD__', template.id),
                title: @js(__('Edit email template')),
                submitLabel: @js(__('Update email template')),
            };
        },
    })"
        @if ($errors->has('recipient')) x-init="$nextTick(() => $dispatch('modal-show', { name: 'email-template-send-test' }))"
        @elseif ($errors->any()) x-init="$nextTick(() => $dispatch('modal-show', { name: 'email-template-form' }))"
        @elseif ($deletedTemplateConflict)
            x-init="$nextTick(() => $dispatch('modal-show', { name: 'email-template-name-conflict' }))"
        @elseif ($list['create'] && request()->boolean('create'))
            x-init="createTemplate(); $nextTick(() => $dispatch('modal-show', { name: 'email-template-form' }))" @endif
        class="flex flex-col gap-y-2 sm:gap-y-3">
        <x-basics13::list.page-header :list="$list" prefix="email-template" :description="__('Subjects, bodies and framing of the emails the application sends.')">
            <x-slot:actions>
                @if ($list['create'])
                    <x-basics13::list.create-action prefix="email-template" :label="__('New email template')"
                        click="createTemplate()" />
                @endif
            </x-slot:actions>
        </x-basics13::list.page-header>

        <x-basics13::list.flash prefix="email-template" />

        @fragment('list-results')
            <x-basics13::list.searchable-results :search="$list['search']" :total="$emailTemplates->total()" :scope="$scope">
                <x-basics13::list.table prefix="email-template" :paginator="$emailTemplates">
                    <x-slot:toolbar>
                        <x-basics13::list.search :search="$list['search']" prefix="email-template" :scope="$scope" />
                    </x-slot:toolbar>

                    <flux:table.columns>
                        <flux:table.column scope="col">{{ __('Name') }}</flux:table.column>
                        <flux:table.column scope="col">{{ __('Subject (en)') }}</flux:table.column>
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
                                            data-test="email-template-name-{{ $row['id'] }}">{{ $row['name'] }}</span>
                                        @if ($row['isDefault'])
                                            <flux:badge size="sm" color="green" data-test="email-template-default-{{ $row['id'] }}">
                                                {{ __('Default') }}
                                            </flux:badge>
                                        @endif
                                    </span>
                                </flux:table.cell>
                                <flux:table.cell class="max-w-[24rem]">
                                    <span class="block max-w-full truncate" title="{{ $row['subject'] }}">{{ $row['subject'] }}</span>
                                </flux:table.cell>
                                <flux:table.cell align="end" sticky class="bg-white dark:bg-zinc-900">
                                    <x-basics13::list.row-actions :actions="$row['actions']" prefix="email-template"
                                        edit-handler="editTemplate" />
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <x-basics13::list.empty-state :narrowed="$list['narrowed']" :colspan="3" :message="$list['emptyMessage']">
                                @if ($list['create'] && ! $list['narrowed'])
                                    <flux:modal.trigger name="email-template-form">
                                        <flux:button size="sm" variant="primary" icon="plus"
                                            x-on:click="createTemplate()"
                                            data-test="email-template-empty-create">
                                            {{ __('New email template') }}
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
            <flux:modal name="email-template-form" aria-labelledby="email-template-form-heading"
                flyout variant="floating" position="right"
                x-on:close="window.clearForm($el.querySelector('form')); window.resetTrackedForms($el)"
                x-on:cancel.prevent="window.requestTrackedModalClose($el)"
                class="form-drawer max-w-none">
                @include('basics13::email-templates.form')
            </flux:modal>

            <x-basics13::list.send-test-modal prefix="email-template" />

            @if ($deletedTemplateConflict)
                <x-basics13::name-conflict-modal name="email-template-name-conflict" :title="__('Record Name already in trash')"
                    :message="__('A deleted record already uses the name :name.', ['name' => $deletedTemplateConflict['name']])"
                    :create-action="route('email-templates.store')"
                    :restore-action="route('email-templates.trash.restore', $deletedTemplateConflict['id'])"
                    :create-fields="array_intersect_key(old(), $emptyForm)"
                    :create-label="__('Create a new email template')" :restore-label="__('Restore the deleted record instead')"
                    create-test="email-template-conflict-create-new"
                    restore-test="email-template-conflict-restore" />
            @endif
        @endif

        <x-basics13::list.confirm-modal prefix="email-template" />
    </div>
</x-layouts::app>
