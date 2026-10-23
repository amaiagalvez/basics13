<?php

namespace Basics13\Http\Controllers;

use Illuminate\View\View;
use Basics13\Models\UserType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\Eloquent\Model;
use Basics13\Http\Requests\UserTypeListRequest;
use Basics13\Queries\UserTypes\UserTypeListQuery;
use Basics13\Http\Requests\UserTypeDestroyRequest;
use Basics13\Http\Requests\UserTypeRestoreRequest;
use Basics13\Transformers\UserTypeListTransformer;

/**
 * @extends TrashController<UserType>
 */
class UserTypeTrashController extends TrashController
{
    public function index(
        UserTypeListRequest $request,
        UserTypeListQuery $query,
        UserTypeListTransformer $transformer,
    ): View|string {
        $search = $request->search();
        $userTypes = $query->trashed($search);

        return $this->listView($request, 'basics13::user-types.list', [
            'userTypes' => $userTypes,
            'list' => $transformer->trash(
                $userTypes,
                $search,
                $query->stateCounts(trashedTotal: $search === '' ? $userTypes->total() : null),
            ),
            'scope' => 'trashed',
        ]);
    }

    public function restore(UserTypeRestoreRequest $request): RedirectResponse
    {
        return $this->restoreTrashed($request);
    }

    public function destroy(UserTypeDestroyRequest $request): RedirectResponse
    {
        return $this->destroyTrashed($request);
    }

    /**
     * @param  UserType  $record
     */
    protected function restoreTrashedRecord(Model $record): void
    {
        $record->restore();
    }

    /**
     * A type is named in four languages and restoring it puts all four back, so any of them being
     * taken by a live type makes the restore impossible.
     *
     * @param  UserType  $record
     */
    protected function nameIsTaken(Model $record): bool
    {
        foreach (UserType::NAME_FIELDS as $column) {
            $name = $record->getAttribute($column);

            if ($name !== null && UserType::query()->where($column, $name)->exists()) {
                return true;
            }
        }

        return false;
    }

    protected function recordClass(): string
    {
        return UserType::class;
    }

    protected function trashRoute(): string
    {
        return 'user-types.trash.index';
    }
}
