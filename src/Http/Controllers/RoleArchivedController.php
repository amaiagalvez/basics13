<?php

namespace Basics13\Http\Controllers;

use Basics13\Models\Role;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Basics13\Queries\Roles\RoleListQuery;
use Basics13\Http\Requests\RoleListRequest;
use Basics13\Transformers\RoleListTransformer;

class RoleArchivedController extends ArchivedController
{
    public function index(
        RoleListRequest $request,
        RoleListQuery $query,
        RoleListTransformer $transformer,
    ): View|string {
        $search = $request->search();
        $roles = $query->archived($search);

        return $this->listView($request, 'basics13::roles.list', [
            'roles' => $roles,
            'list' => $transformer->archived(
                $roles,
                $search,
                $query->stateCounts(archivedTotal: $search === '' ? $roles->total() : null),
            ),
            'scope' => 'archived',
        ]);
    }

    public function archive(Role $role): RedirectResponse
    {
        return $this->archiveRecord($role);
    }

    public function activate(Role $role): RedirectResponse
    {
        return $this->activateRecord($role);
    }

    protected function activeRoute(): string
    {
        return 'roles.index';
    }

    protected function archivedRoute(): string
    {
        return 'roles.archived.index';
    }
}
