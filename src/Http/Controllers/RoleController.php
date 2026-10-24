<?php

namespace Basics13\Http\Controllers;

use Basics13\Models\Role;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Basics13\Http\Requests\RoleRequest;
use Illuminate\Database\QueryException;
use Basics13\Queries\Roles\RoleListQuery;
use Basics13\Http\Requests\RoleListRequest;
use Basics13\Transformers\RoleListTransformer;
use Basics13\Queries\Permissions\PermissionListQuery;
use Basics13\Support\Database\UniqueConstraintViolation;

class RoleController extends Controller
{
    public function index(
        RoleListRequest $request,
        RoleListQuery $query,
        RoleListTransformer $transformer,
        PermissionListQuery $permissions,
    ): View|string {
        $search = $request->search();
        $roles = $query->active($search);

        return $this->listView($request, 'basics13::roles.list', [
            'roles' => $roles,
            'permissionsByModule' => $permissions->byModule(),
            'list' => $transformer->active(
                $roles,
                $search,
                $query->stateCounts(activeTotal: $search === '' ? $roles->total() : null),
            ),
            'scope' => 'active',
        ]);
    }

    public function store(RoleRequest $request, RoleListQuery $query): RedirectResponse|JsonResponse
    {
        $deletedRole = $query->findTrashedByName($request->string('name')->toString());

        if ($deletedRole !== null && ! $request->boolean('reuse_deleted_name')) {
            return $this->deletedNameConflict($request, $deletedRole, 'roles.index', 'deleted_role_conflict');
        }

        try {
            DB::transaction(function () use ($request): void {
                $role = Role::create($this->attributes($request));
                $role->permissions()->sync($request->permissionIds());
            });
        } catch (QueryException $exception) {
            $this->failOnDuplicatedRole($exception);
        }

        return $this->backToList('roles.index')->with('status', __('basics13::messages.created'));
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $role): void {
                $role->update($this->attributes($request));
                $role->permissions()->sync($request->permissionIds());
            });
        } catch (QueryException $exception) {
            $this->failOnDuplicatedRole($exception);
        }

        return $this->backToList('roles.index')->with('status', __('basics13::messages.updated'));
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        // The deleting hook holds the row lock and refuses the delete while an account holds the
        // role, so the controller only has to run it inside a transaction and report the outcome.
        $deleted = DB::transaction(static fn (): bool => $role->delete() !== false);

        if (! $deleted) {
            return $this->backToList('roles.index')
                ->with('error', __('basics13::messages.cannot_delete_related'));
        }

        return $this->backToList('roles.index')->with('status', __('basics13::messages.moved_to_trash'));
    }

    /**
     * The role columns, without the permissions: those live in the pivot table.
     *
     * @return array<string, mixed>
     */
    private function attributes(RoleRequest $request): array
    {
        $attributes = $request->validated();
        unset($attributes['permission_ids'], $attributes['reuse_deleted_name']);

        return $attributes;
    }

    /**
     * A write that passed validation and still hit a unique index lost a race against a concurrent
     * one. The index the database names tells whether the name or the slug collided: MySQL names
     * the index (`roles_active_slug_unique`), SQLite and PostgreSQL the column (`BAS_roles.slug`).
     */
    private function failOnDuplicatedRole(QueryException $exception): never
    {
        $message = (string) ($exception->errorInfo[2] ?? $exception->getMessage());
        $field = str_contains($message, 'active_slug') || str_contains($message, '.slug') ? 'slug' : 'name';

        UniqueConstraintViolation::rethrowAsValidationError($exception, $field);
    }
}
