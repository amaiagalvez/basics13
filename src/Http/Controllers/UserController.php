<?php

namespace Basics13\Http\Controllers;

use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Basics13\Http\Requests\UserRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Basics13\Concerns\ResolvesUserModel;
use Basics13\Queries\Users\UserListQuery;
use Basics13\Http\Requests\UserListRequest;
use Basics13\Transformers\UserListTransformer;
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
        $users = $query->active($search, $filters);

        return $this->listView($request, 'basics13::users.list', [
            'users' => $users,
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
            'results' => $query->search($request->search()),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        try {
            $modelClass = $this->userModelClass();
            $modelClass::query()->create($this->attributes($request));
        } catch (QueryException $exception) {
            UniqueConstraintViolation::rethrowAsValidationError($exception, 'email');
        }

        return to_route('users.index')->with('status', __('basics13::messages.created'));
    }

    public function update(UserRequest $request, Model $user): RedirectResponse
    {
        try {
            $user->update($this->attributes($request));
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
     * The validated columns, without a null password: an edit that leaves the field out keeps the
     * current one, so the null must never reach the update.
     *
     * @return array<string, mixed>
     */
    private function attributes(UserRequest $request): array
    {
        $attributes = $request->validated();

        if (($attributes['password'] ?? null) === null) {
            unset($attributes['password']);
        }

        return $attributes;
    }
}
