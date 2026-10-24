<?php

namespace Basics13\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Database\Eloquent\Model;

/**
 * Archives and activates a record.
 *
 * Both actions are the same for every resource, so each controller only names the routes it
 * returns to. The signatures keep the concrete model because Laravel binds the route parameter to
 * the type hint.
 */
abstract class ArchivedController extends Controller
{
    final protected function archiveRecord(Model $record): RedirectResponse
    {
        $this->authorize('archive', $record);

        $record->setAttribute('active', false);
        $record->save();

        return $this->backToList($this->activeRoute())->with('status', __('basics13::messages.archived'));
    }

    final protected function activateRecord(Model $record): RedirectResponse
    {
        $this->authorize('activate', $record);

        $record->setAttribute('active', true);
        $record->save();

        return $this->backToList($this->archivedRoute())->with('status', __('basics13::messages.activated'));
    }

    /**
     * The active list: where the user lands after archiving, since the record has just left it.
     */
    abstract protected function activeRoute(): string;

    /**
     * The archived list: where the user lands after activating, since the record has just left it.
     */
    abstract protected function archivedRoute(): string;
}
