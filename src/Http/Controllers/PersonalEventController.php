<?php

namespace Basics13\Http\Controllers;

use Basics13\Models\PersonalEvent;
use Illuminate\Http\RedirectResponse;
use Basics13\Http\Requests\PersonalEventRequest;

/**
 * Writes the events of the signed-in user's own calendar. The calendar that shows them belongs to
 * whichever package draws one, so every action goes back to the page it was sent from.
 */
class PersonalEventController extends Controller
{
    public function store(PersonalEventRequest $request): RedirectResponse
    {
        $personalEvent = new PersonalEvent($request->validated());
        $personalEvent->user_id = (int) $request->user()?->getAuthIdentifier();
        $personalEvent->save();

        return back()->with('status', __('basics13::messages.created'));
    }

    public function update(PersonalEventRequest $request, PersonalEvent $personalEvent): RedirectResponse
    {
        $personalEvent->update($request->validated());

        return back()->with('status', __('basics13::messages.updated'));
    }

    public function destroy(PersonalEvent $personalEvent): RedirectResponse
    {
        $this->authorize('delete', $personalEvent);

        $personalEvent->delete();

        return back()->with('status', __('basics13::messages.deleted'));
    }
}
