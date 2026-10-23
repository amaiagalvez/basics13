<?php

namespace Basics13\Http\Controllers;

use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Basics13\Http\Requests\UserRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Basics13\Concerns\ResolvesUserModel;
use Basics13\Queries\Users\UserListQuery;
use Basics13\Http\Requests\UserListRequest;
use Basics13\Transformers\UserListTransformer;
use Illuminate\Contracts\Auth\Authenticatable;
use Basics13\Queries\Users\UserSelectOptionsQuery;
use Basics13\Http\Requests\UserSelectOptionsRequest;
use Basics13\Support\Database\UniqueConstraintViolation;

class UserController extends Controller
{
    use ResolvesUserModel;

    public function index(
        UserListRequest $request,
        UserListQuery $query,
        UserListTransformer $transformer,
    ): View|string {
        $search = $request->search();
        $filters = $request->filters() ?? [];
        $users = $query->visibleTo($request->user())->active($search, $filters);

        return $this->listView($request, 'basics13::users.list', [
            'users' => $users,
            // Null when the user cannot assign roles: the form then shows no selector.
            'assignableRoles' => $request->assignsRoles() ? $query->assignableRoles($request->assignsSystemRoles(), $request->roleGrantor()) : null,
            // Null when the user cannot assign types; the admin flag only for a global editor.
            'assignableTypes' => $request->assignsTypes() ? $query->assignableTypes($request->typeAdmin()) : null,
            'assignsTypeAdmins' => $request->typeAdmin() === null,
            'list' => $transformer->active(
                $users,
                $search,
                $query->stateCounts(activeTotal: $search === '' && $filters === [] ? $users->total() : null),
            ),
            'searchableFields' => $query->searchableFields('active'),
            'filters' => $filters,
            'scope' => 'active',
        ]);
    }

    public function selectOptions(
        UserSelectOptionsRequest $request,
        UserSelectOptionsQuery $query,
    ): JsonResponse {
        return response()->json([
            'results' => $query->visibleTo($request->user())->search($request->search()),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request): void {
                $modelClass = $this->userModelClass();
                $user = $modelClass::query()->create($this->attributes($request));
                $this->syncRoles($request, $user);
                $this->syncTypes($request, $user);
            });
        } catch (QueryException $exception) {
            UniqueConstraintViolation::rethrowAsValidationError($exception, 'email');
        }

        return to_route('users.index')->with('status', __('basics13::messages.created'));
    }

    public function update(UserRequest $request, Model $user): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $user): void {
                $user->update($this->attributes($request));
                $this->syncRoles($request, $user);
                $this->syncTypes($request, $user);
            });
        } catch (QueryException $exception) {
            UniqueConstraintViolation::rethrowAsValidationError($exception, 'email');
        }

        return to_route('users.index')->with('status', __('basics13::messages.updated'));
    }

    public function destroy(Model $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        // A trashed account keeps its audit trail and blocks nothing else, so the delete is the
        // model's own: the controller only reports the outcome.
        $deleted = $user->delete() !== false;

        if (! $deleted) {
            return to_route('users.index')
                ->with('error', __('basics13::messages.cannot_delete_related'));
        }

        return to_route('users.index')->with('status', __('basics13::messages.moved_to_trash'));
    }

    /**
     * Leaves the account with the roles the form chose, stamped with who assigned them. A form
     * without the role selector leaves them as they are.
     */
    private function syncRoles(UserRequest $request, Model $user): void
    {
        $roleIds = $request->roleIds();

        if ($roleIds === null) {
            return;
        }

        // The configured users model uses the HasRoles concern, which PHPStan cannot see through
        // the shared `Model` base.
        /** @phpstan-ignore method.notFound */
        $user->syncRoles($roleIds, (int) $request->user()?->getAuthIdentifier());
    }

    /**
     * Leaves the account with the types the form chose and the admin flag of each. A form without
     * the type selector leaves them as they are.
     */
    private function syncTypes(UserRequest $request, Model $user): void
    {
        $types = $request->types();

        if ($types === null) {
            return;
        }

        // The configured users model uses the HasRoles concern, which PHPStan cannot see through
        // the shared `Model` base.
        /** @phpstan-ignore method.notFound */
        $user->userTypes()->sync($types);
    }

    /**
     * The validated columns, without a null password: an edit that leaves the field out keeps the
     * current one, so the null must never reach the update.
     *
     * @return array<string, mixed>
     */
    private function attributes(UserRequest $request): array
    {
        $attributes = $request->validated();
        unset($attributes['assign_roles'], $attributes['role_ids'], $attributes['assign_types'], $attributes['user_type_ids'], $attributes['admin_type_ids']);

        if (($attributes['password'] ?? null) === null) {
            unset($attributes['password']);
        }

        return $attributes;
    }

    /**
     * Start impersonating the given user.
     *
     * @param  Model&Authenticatable  $user
     */
    public function impersonate(Model $user): RedirectResponse
    {
        $this->authorize('impersonate', $user);

        $originalId = Auth::id();
        // Before the login, so the login audit records the row marked with its impersonator.
        session(['impersonator_id' => $originalId]);
        Auth::login($user);

        return redirect()->route('dashboard')->with('status', __('basics13::messages.impersonation_started', ['name' => $user->getAttribute('name')]));
    }

    /**
     * Leave the current impersonation and return to the original user.
     */
    public function leaveImpersonation(): RedirectResponse
    {
        if (! session()->has('impersonator_id')) {
            return redirect()->route('dashboard')->with('error', __('basics13::messages.no_active_impersonation'));
        }

        $originalId = session('impersonator_id');
        /** @var Authenticatable $originalUser */
        $originalUser = $this->userModelClass()::findOrFail($originalId);
        Auth::login($originalUser);
        // After the login, so the login audit sees the impersonation and closes its row.
        session()->forget('impersonator_id');

        return redirect()->route('users.index')->with('status', __('basics13::messages.impersonation_ended'));
    }
}
