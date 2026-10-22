<?php

namespace Basics13\Http\Controllers;

use Basics13\Models\Role;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\Eloquent\Model;
use Basics13\Queries\Roles\RoleListQuery;
use Basics13\Http\Requests\RoleListRequest;
use Basics13\Http\Requests\RoleDestroyRequest;
use Basics13\Http\Requests\RoleRestoreRequest;
use Basics13\Transformers\RoleListTransformer;

/**
 * @extends TrashController<Role>
 */
class RoleTrashController extends TrashController
{
    public function index(
        RoleListRequest $request,
        RoleListQuery $query,
        RoleListTransformer $transformer,
    ): View|string {
        $search = $request->search();
        $roles = $query->trashed($search);

        return $this->listView($request, 'basics13::roles.list', [
            'roles' => $roles,
            'list' => $transformer->trash(
                $roles,
                $search,
                $query->stateCounts(trashedTotal: $search === '' ? $roles->total() : null),
            ),
            'scope' => 'trashed',
        ]);
    }

    public function restore(RoleRestoreRequest $request): RedirectResponse
    {
        return $this->restoreTrashed($request);
    }

    public function destroy(RoleDestroyRequest $request): RedirectResponse
    {
        return $this->destroyTrashed($request);
    }

    /**
     * @param  Role  $record
     */
    protected function restoreTrashedRecord(Model $record): void
    {
        $record->restore();
    }

    /**
     * Restoring puts both the name and the slug back, so either of them being taken by a role
     * outside the trash makes the restore impossible.
     *
     * @param  Role  $record
     */
    protected function nameIsTaken(Model $record): bool
    {
        return $this->takenBy(Role::query(), $record->name)
            || Role::query()->where('slug', $record->slug)->exists();
    }

    protected function recordClass(): string
    {
        return Role::class;
    }

    protected function trashRoute(): string
    {
        return 'roles.trash.index';
    }
}
