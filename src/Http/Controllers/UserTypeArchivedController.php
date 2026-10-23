<?php

namespace Basics13\Http\Controllers;

use Illuminate\View\View;
use Basics13\Models\UserType;
use Illuminate\Http\RedirectResponse;
use Basics13\Http\Requests\UserTypeListRequest;
use Basics13\Queries\UserTypes\UserTypeListQuery;
use Basics13\Transformers\UserTypeListTransformer;

class UserTypeArchivedController extends ArchivedController
{
    public function index(
        UserTypeListRequest $request,
        UserTypeListQuery $query,
        UserTypeListTransformer $transformer,
    ): View|string {
        $search = $request->search();
        $userTypes = $query->archived($search);

        return $this->listView($request, 'basics13::user-types.list', [
            'userTypes' => $userTypes,
            'list' => $transformer->archived(
                $userTypes,
                $search,
                $query->stateCounts(archivedTotal: $search === '' ? $userTypes->total() : null),
            ),
            'scope' => 'archived',
        ]);
    }

    public function archive(UserType $userType): RedirectResponse
    {
        return $this->archiveRecord($userType);
    }

    public function activate(UserType $userType): RedirectResponse
    {
        return $this->activateRecord($userType);
    }

    protected function activeRoute(): string
    {
        return 'user-types.index';
    }

    protected function archivedRoute(): string
    {
        return 'user-types.archived.index';
    }
}
