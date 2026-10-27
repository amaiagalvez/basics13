<?php

namespace Basics13\Transformers;

use Basics13\Enums\Language;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

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
            'roles' => $this->roleNames($record),
            'types' => $this->typeNames($record),
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
        return true;
    }

    protected function getForceDeleteBlockHint(Model $record): string
    {
        return __('basics13::messages.user_force_delete_disabled');
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
            'own' => (int) $record->getKey() === (int) Auth::id(),
            'role_ids' => $this->roleIds($record),
            'user_type_ids' => $this->typeIds($record),
            'admin_type_ids' => $this->typeIds($record, adminOnly: true),
        ];
    }

    /**
     * The types the account holds, or only those it administers, as the strings the checkboxes of
     * the form carry.
     *
     * @return list<string>
     */
    private function typeIds(Model $record, bool $adminOnly = false): array
    {
        $types = $record->relationLoaded('userTypes') ? $record->getRelation('userTypes') : null;

        if (! $types instanceof Collection) {
            return [];
        }

        return array_values($types
            ->filter(static fn (Model $type): bool => ! $adminOnly || (bool) $type->getRelationValue('pivot')?->getAttribute('is_admin'))
            ->map(static fn (Model $type): string => (string) $type->getKey())
            ->all());
    }

    /**
     * The types the account holds, by Basque name, for the list column, flagged where the account
     * is an admin.
     *
     * @return list<array{name: string, admin: bool}>
     */
    private function typeNames(Model $record): array
    {
        $types = $record->relationLoaded('userTypes') ? $record->getRelation('userTypes') : null;

        if (! $types instanceof Collection) {
            return [];
        }

        return array_values($types
            ->sortBy(static fn (Model $type): string => (string) $type->getAttribute('name_eu'))
            ->map(static fn (Model $type): array => [
                'name' => (string) $type->getAttribute('name_eu'),
                'admin' => (bool) $type->getRelationValue('pivot')?->getAttribute('is_admin'),
            ])
            ->all());
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
     * The roles the account holds, by name, for the list column. An archived role grants nothing,
     * so it is flagged for the badge to say so.
     *
     * @return list<array{name: string, active: bool}>
     */
    private function roleNames(Model $record): array
    {
        $roles = $record->relationLoaded('roles') ? $record->getRelation('roles') : null;

        if (! $roles instanceof Collection) {
            return [];
        }

        return array_values($roles
            ->sortBy(static fn (Model $role): string => (string) $role->getAttribute('name'))
            ->map(static fn (Model $role): array => [
                'name' => (string) $role->getAttribute('name'),
                'active' => (bool) $role->getAttribute('active'),
            ])
            ->all());
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

    /**
     * Impersonate action for superadmins on non-superadmin users (not themselves).
     *
     * @return array<string, mixed>
     */
    protected function impersonateAction(Model $record): array
    {
        return [
            'type' => 'confirm-modal',
            'label' => __('actions.impersonate'),
            'icon' => 'user',
            'test' => $this->resourceKey().'-impersonate-'.$this->key($record),
            'danger' => false,
            'action' => route('users.impersonate', $record),
            'method' => 'POST',
            'confirmTitle' => __('basics13::messages.impersonate_confirm_title', ['name' => $this->recordName($record)]),
            'confirmText' => __('basics13::messages.impersonate_confirm_text', ['name' => $this->recordName($record)]),
            'confirmLabel' => __('actions.impersonate'),
        ];
    }

    /**
     * Active list view: rows with edit, delete/archive, and impersonate actions.
     *
     * @param  LengthAwarePaginator<int, Model>  $records
     * @param  array{active: int, archived: int, trashed: int}|null  $counts
     * @return array<string, mixed>
     */
    public function active(LengthAwarePaginator $records, string $search, ?array $counts = null): array
    {
        return [
            ...$this->envelope('active', $search, $counts),
            'rows' => collect($records->items())->map(function (Model $record): array {
                $extra = $this->integrationRow($record);
                $actions = [
                    $this->editAction($record),
                    $this->getDeleteBlockCondition($record)
                        ? $this->archiveAction($record, __('basics13::messages.archive_instead_of_delete'))
                        : $this->deleteAction($record),
                ];

                // Add impersonate action for superadmins on non-superadmin users (not themselves)
                $currentUser = Auth::user();
                if ($currentUser !== null
                    /** @phpstan-ignore function.alreadyNarrowedType (the host may configure another user model) */
                    && method_exists($currentUser, 'isSuperAdmin')
                    && $currentUser->isSuperAdmin()
                    && ! (bool) $record->getAttribute('is_super_admin')
                    && (int) $record->getKey() !== (int) $currentUser->getAuthIdentifier()
                ) {
                    $actions[] = $this->impersonateAction($record);
                }

                return [
                    ...$this->columns($record, $extra),
                    'extraDate' => $this->getExtraDate($record, 'active'),
                    'actions' => $actions,
                ];
            })->all(),
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
