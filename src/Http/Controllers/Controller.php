<?php

namespace Basics13\Http\Controllers;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Render a list view, or only its results fragment when the list is being refreshed.
     *
     * @param  view-string  $view
     * @param  array<string, mixed>  $data
     */
    protected function listView(Request $request, string $view, array $data): View|string
    {
        $view = view($view, $data);

        return $request->hasHeader('X-List-Fragment')
            ? $view->fragment('list-results')
            : $view;
    }

    /**
     * A record in the trash already uses the name the user is trying to create. The browser is sent
     * back to its list with the conflict modal open; a JSON caller gets a 409 it can render on the
     * name field. Each resource supplies its route and session key explicitly.
     *
     * @param  Model&object{name: string}  $trashedRecord  the trashed record holding the name
     */
    protected function deletedNameConflict(
        Request $request,
        Model $trashedRecord,
        string $indexRoute,
        string $sessionKey,
    ): RedirectResponse|JsonResponse {
        if ($request->expectsJson()) {
            $message = __('basics13::messages.deleted_name_conflict', ['name' => $trashedRecord->name]);

            return response()->json([
                'message' => $message,
                'errors' => ['name' => [$message]],
            ], 409);
        }

        return to_route($indexRoute)
            ->withInput()
            ->with($sessionKey, [
                'id' => $trashedRecord->getKey(),
                'name' => $trashedRecord->name,
            ]);
    }
}
