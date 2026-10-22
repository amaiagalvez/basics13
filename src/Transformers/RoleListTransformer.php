<?php

namespace Basics13\Transformers;

use Basics13\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Basics13\Concerns\ChecksPermissions;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends ListTransformer<Role>
 */
class RoleListTransformer extends ListTransformer
{
    use ChecksPermissions;

    /**
     * A system role is never archived or deleted, and only the super-admin edits it, so its row
     * swaps those actions for locked ones that say why.
     *
     * @param  LengthAwarePaginator<int, Role>  $records
     * @param  array{active: int, archived: int, trashed: int}|null  $counts
     * @return array<string, mixed>
     */
    public function active(LengthAwarePaginator $records, string $search, ?array $counts = null): array
    {
        $list = parent::active($records, $search, $counts);
        $user = auth()->user();
        $editsSystemRoles = $user !== null && $this->isSuperAdmin($user);

        $list['rows'] = array_map(fn (array $row): array => $row['isSystem'] ? [
            ...$row,
            'actions' => [
                $editsSystemRoles
                    ? $row['actions'][0]
                    : $this->blockedAction(
                        __('basics13::messages.edit'),
                        'lock-closed',
                        'role-edit-blocked-'.$row['id'],
                        __('Only the super-admin edits a system role.'),
                    ),
                $this->blockedAction(
                    __('basics13::messages.move_to_trash'),
                    'lock-closed',
                    'role-delete-blocked-'.$row['id'],
                    __('A system role cannot be archived or deleted.'),
                ),
            ],
        ] : $row, $list['rows']);

        return $list;
    }

    /**
     * @param  Role  $record
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function columns(Model $record, array $extra): array
    {
        return [
            'id' => $record->id,
            'name' => $record->name,
            'slug' => $record->slug,
            'description' => $record->description,
            'isSystem' => $record->is_system,
            'usersCount' => (int) $record->getAttribute('users_count'),
            'permissionsCount' => (int) $record->getAttribute('permissions_count'),
            'editPayload' => $this->editPayload($record),
            ...$extra,
        ];
    }

    /**
     * A role an account holds is archived instead of deleted. The count takes trashed accounts in,
     * because those keep the role too.
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
     * @param  Role  $record
     * @return array<string, mixed>
     */
    protected function editPayload(Model $record): array
    {
        return [
            ...$record->only(['id', 'name', 'slug', 'description', 'notes', 'is_system']),
            'permission_ids' => $record->relationLoaded('permissions')
                ? array_values($record->permissions->map(static fn (Model $permission): string => (string) $permission->getKey())->all())
                : [],
        ];
    }

    protected function resourceLabel(): string
    {
        return __('Roles');
    }

    protected function resourceKey(): string
    {
        return 'role';
    }
}
