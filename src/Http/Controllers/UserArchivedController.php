<?php

namespace Basics13\Http\Controllers;

use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\Eloquent\Model;
use Basics13\Queries\Users\UserListQuery;
use Basics13\Http\Requests\UserListRequest;
use Basics13\Transformers\UserListTransformer;

class UserArchivedController extends ArchivedController
{
    public function index(
        UserListRequest $request,
        UserListQuery $query,
        UserListTransformer $transformer,
    ): View|string {
        $search = $request->search();
        $users = $query->archived($search);

        return $this->listView($request, 'basics13::users.list', [
            'users' => $users,
            'list' => $transformer->archived(
                $users,
                $search,
                $query->stateCounts(archivedTotal: $search === '' ? $users->total() : null),
            ),
        ]);
    }

    public function archive(Model $user): RedirectResponse
    {
        return $this->archiveRecord($user);
    }

    public function activate(Model $user): RedirectResponse
    {
        return $this->activateRecord($user);
    }

    protected function activeRoute(): string
    {
        return 'users.index';
    }

    protected function archivedRoute(): string
    {
        return 'users.archived.index';
    }
}
