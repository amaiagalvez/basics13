<?php

namespace Basics13\Transformers;

use Illuminate\Database\Eloquent\Model;

/**
 * @extends ListTransformer<Model>
 */
class UserListTransformer extends ListTransformer
{
    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function columns(Model $record, array $extra): array
    {
        return [
            'id' => $record->getKey(),
            'name' => $record->getAttribute('name'),
            'email' => $record->getAttribute('email'),
            'editPayload' => $this->editPayload($record),
            ...$extra,
        ];
    }

    protected function getDeleteBlockCondition(Model $record): bool
    {
        return false;
    }

    protected function getForceDeleteBlockCondition(Model $record): bool
    {
        return false;
    }

    protected function getForceDeleteBlockHint(Model $record): string
    {
        return '';
    }

    /**
     * Fields the drawer needs to edit the account. Never carries the password: it is not readable
     * and an edit that leaves the field out keeps the current one. `own` tells the drawer whether
     * the password and the email can change: only the account holder may change them.
     *
     * @return array<string, mixed>
     */
    protected function editPayload(Model $record): array
    {
        return [
            ...$record->only(['id', 'name', 'email', 'notes']),
            'own' => (int) $record->getKey() === (int) auth()->id(),
        ];
    }

    protected function resourceLabel(): string
    {
        return __('Users');
    }

    protected function resourceKey(): string
    {
        return 'user';
    }
}
