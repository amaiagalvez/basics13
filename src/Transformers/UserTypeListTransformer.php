<?php

namespace Basics13\Transformers;

use Basics13\Models\UserType;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends ListTransformer<UserType>
 */
class UserTypeListTransformer extends ListTransformer
{
    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function columns(Model $record, array $extra): array
    {
        return [
            'id' => $record->id,
            'nameEu' => $record->name_eu,
            'nameEs' => $record->name_es,
            'nameFr' => $record->name_fr,
            'nameEn' => $record->name_en,
            'editPayload' => $this->editPayload($record),
            ...$extra,
        ];
    }

    /**
     * A type that an account holds is archived instead of deleted: it stays selectable in the
     * account form, which is where the type gets used from. The count is taken with the deleted
     * accounts in, because those keep the type too.
     */
    protected function getDeleteBlockCondition(Model $record): bool
    {
        return (int) $record->getAttribute('users_count') > 0;
    }

    protected function getForceDeleteBlockCondition(Model $record): bool
    {
        return (int) $record->getAttribute('users_count') > 0;
    }

    protected function getForceDeleteBlockHint(Model $record): string
    {
        return __('basics13::messages.cannot_force_delete_related');
    }

    /**
     * @param  UserType  $record
     * @return array{id: int, name_eu: string, name_es: string|null, name_fr: string|null, name_en: string|null, notes: string|null}
     */
    protected function editPayload(Model $record): array
    {
        return $record->only(['id', 'name_eu', 'name_es', 'name_fr', 'name_en', 'notes']);
    }

    /**
     * A type has no single name: it is named by its Basque one, the only required translation.
     *
     * @param  UserType  $record
     */
    protected function recordName(Model $record): string
    {
        return $record->name_eu;
    }

    protected function resourceLabel(): string
    {
        return __('User types');
    }

    protected function resourceKey(): string
    {
        return 'user-type';
    }
}
