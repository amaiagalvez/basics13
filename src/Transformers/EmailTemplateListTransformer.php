<?php

namespace Basics13\Transformers;

use Basics13\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends ListTransformer<EmailTemplate>
 */
class EmailTemplateListTransformer extends ListTransformer
{
    /**
     * Every active row also offers a test email, sent from a modal that asks for the recipient.
     *
     * @param  LengthAwarePaginator<int, EmailTemplate>  $records
     * @param  array{active: int, archived: int, trashed: int}|null  $counts
     * @return array<string, mixed>
     */
    public function active(LengthAwarePaginator $records, string $search, ?array $counts = null): array
    {
        $list = parent::active($records, $search, $counts);

        $list['rows'] = array_map(fn (array $row): array => [
            ...$row,
            'actions' => [...$row['actions'], $this->sendTestAction($row)],
        ], $list['rows']);

        return $list;
    }

    /**
     * @param  EmailTemplate  $record
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function columns(Model $record, array $extra): array
    {
        return [
            'id' => $record->id,
            'name' => $record->name,
            'subject' => $record->subject_en,
            'isDefault' => $record->default,
            'editPayload' => $this->editPayload($record),
            ...$extra,
        ];
    }

    /**
     * Nothing points at a email template, so it always goes to the trash.
     */
    protected function getDeleteBlockCondition(Model $record): bool
    {
        return false;
    }

    /**
     * @param  EmailTemplate  $record
     */
    protected function getForceDeleteBlockCondition(Model $record): bool
    {
        return $record->default;
    }

    protected function getForceDeleteBlockHint(Model $record): string
    {
        return __('The default one cannot be deleted permanently. Mark another one as the default first.');
    }

    /**
     * @param  EmailTemplate  $record
     * @return array<string, mixed>
     */
    protected function editPayload(Model $record): array
    {
        return $record->only([
            'id', 'name', 'banner', 'legal_text',
            'subject_eu', 'subject_es', 'subject_fr', 'subject_en',
            'body_eu', 'body_es', 'body_fr', 'body_en',
            'default', 'notes',
        ]);
    }

    protected function resourceLabel(): string
    {
        return __('Email templates');
    }

    protected function resourceKey(): string
    {
        return 'email-template';
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function sendTestAction(array $row): array
    {
        return [
            'type' => 'modal',
            'modal' => 'email-template-send-test',
            'handler' => 'openSendTest',
            'label' => __('Send test email'),
            'icon' => 'paper-airplane',
            'test' => 'email-template-send-test-'.$row['id'],
            'payload' => [
                'action' => route('email-templates.send-test', $row['id']),
                'name' => $row['name'],
            ],
        ];
    }
}
