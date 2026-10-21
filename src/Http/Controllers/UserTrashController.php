<?php

namespace Basics13\Http\Controllers;

use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\Eloquent\Model;
use Basics13\Concerns\ResolvesUserModel;
use Basics13\Queries\Users\UserListQuery;
use Basics13\Http\Requests\UserListRequest;
use Basics13\Http\Requests\UserRestoreRequest;
use Basics13\Transformers\UserListTransformer;
use Basics13\Http\Requests\UserTrashDestroyRequest;

/**
 * @extends TrashController<Model>
 */
class UserTrashController extends TrashController
{
    use ResolvesUserModel;

    public function index(
        UserListRequest $request,
        UserListQuery $query,
        UserListTransformer $transformer,
    ): View|string {
        $search = $request->search();
        $filters = $request->filters() ?? [];
        $users = $query->trashed($search, $filters);

        return $this->listView($request, 'basics13::users.list', [
            'users' => $users,
            'list' => $transformer->trash(
                $users,
                $search,
                $query->stateCounts(trashedTotal: $search === '' && $filters === [] ? $users->total() : null),
            ),
            'searchableFields' => $query->searchableFields('trashed'),
            'filters' => $filters,
            'scope' => 'trashed',
        ]);
    }

    public function restore(UserRestoreRequest $request): RedirectResponse
    {
        return $this->restoreTrashed($request);
    }

    public function destroy(UserTrashDestroyRequest $request): RedirectResponse
    {
        return $this->destroyTrashed($request);
    }

    protected function restoreTrashedRecord(Model $record): void
    {
        // The configured model is soft deletable, which PHPStan cannot see through the base `Model`.
        // Restoring the instance rather than the query is what lets the model's `restoring` hook
        // clear `deleted_by` before the row is saved back.
        /** @phpstan-ignore method.notFound */
        $record->restore();
    }

    /**
     * The email is unique across every account, trashed ones included, so an active account can
     * never be sitting on the email of a trashed one: a restore has no identity to collide with.
     */
    protected function nameIsTaken(Model $record): bool
    {
        return false;
    }

    protected function recordClass(): string
    {
        return $this->userModelClass();
    }

    protected function trashRoute(): string
    {
        return 'users.trash.index';
    }
}
