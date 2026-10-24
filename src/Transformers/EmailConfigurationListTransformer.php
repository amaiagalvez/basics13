<?php

namespace Basics13\Transformers;

use Basics13\Models\EmailConfiguration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends ListTransformer<EmailConfiguration>
 */
class EmailConfigurationListTransformer extends ListTransformer
{
    /**
     * Every active row also offers a test email, sent from a modal that asks for the recipient.
     *
     * @param  LengthAwarePaginator<int, EmailConfiguration>  $records
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
     * @param  EmailConfiguration  $record
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function columns(Model $record, array $extra): array
    {
        return [
            'id' => $record->id,
            'name' => $record->name,
            'host' => $record->host,
            'port' => $record->port,
            'senderEmail' => $record->sender_email,
            'encryption' => $record->encryption,
            'isDefault' => $record->default,
            'editPayload' => $this->editPayload($record),
            ...$extra,
        ];
    }

    /**
     * Nothing points at a SMTP configuration, so it always goes to the trash.
     */
    protected function getDeleteBlockCondition(Model $record): bool
    {
        return false;
    }

    /**
     * @param  EmailConfiguration  $record
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
     * @param  EmailConfiguration  $record
     * @return array<string, mixed>
     */
    protected function editPayload(Model $record): array
    {
        return [
            ...$record->only(['id', 'name', 'sender_name', 'sender_email', 'host', 'port', 'username', 'encryption', 'default', 'notes']),
            // The stored password never leaves the server: the field always opens empty.
            'password' => '',
        ];
    }

    protected function resourceLabel(): string
    {
        return __('SMTP configurations');
    }

    protected function resourceKey(): string
    {
        return 'email-configuration';
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function sendTestAction(array $row): array
    {
        return [
            'type' => 'modal',
            'modal' => 'email-configuration-send-test',
            'handler' => 'openSendTest',
            'label' => __('Send test email'),
            'icon' => 'paper-airplane',
            'test' => 'email-configuration-send-test-'.$row['id'],
            'payload' => [
                'action' => route('email-configurations.send-test', $row['id']),
                'name' => $row['name'],
            ],
        ];
    }
}
