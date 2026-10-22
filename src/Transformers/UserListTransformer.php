<?php

namespace Basics13\Transformers;

use Basics13\Enums\Language;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

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
            'lang' => $this->languageName($record),
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
     * the password, the email and the roles can change: only the account holder changes the first
     * two, and nobody changes their own roles.
     *
     * @return array<string, mixed>
     */
    protected function editPayload(Model $record): array
    {
        return [
            ...$record->only(['id', 'name', 'email', 'lang', 'notes']),
            'own' => (int) $record->getKey() === (int) auth()->id(),
            'role_ids' => $this->roleIds($record),
        ];
    }

    /**
     * The roles the account holds, as the strings the checkboxes of the form carry.
     *
     * @return list<string>
     */
    private function roleIds(Model $record): array
    {
        $roles = $record->relationLoaded('roles') ? $record->getRelation('roles') : null;

        if (! $roles instanceof Collection) {
            return [];
        }

        return array_values($roles->map(static fn (Model $role): string => (string) $role->getKey())->all());
    }

    /**
     * The native name of the stored language, or nothing when the user has none or it is no longer
     * offered.
     */
    private function languageName(Model $record): string
    {
        $lang = $record->getAttribute('lang');

        return is_string($lang) ? (Language::tryFrom($lang)?->nativeName() ?? '') : '';
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
