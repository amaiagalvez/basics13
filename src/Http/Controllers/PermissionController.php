<?php

namespace Basics13\Http\Controllers;

use Illuminate\View\View;
use Basics13\Http\Requests\PermissionListRequest;
use Basics13\Queries\Permissions\PermissionListQuery;

/**
 * Permissions are seeded by the package that owns each module and only listed here: the policies
 * check fixed slugs, so creating, renaming or deleting one from the interface would either do
 * nothing or break those checks.
 */
class PermissionController extends Controller
{
    public function index(PermissionListRequest $request, PermissionListQuery $query): View
    {
        return view('basics13::permissions.list', [
            'permissionsByModule' => $query->byModuleWithRoles(),
        ]);
    }
}
