<?php

namespace Basics13\Http\Controllers;

use Illuminate\View\View;
use Basics13\Http\Requests\SessionDashboardRequest;
use Basics13\Queries\Sessions\SessionDashboardQuery;

/**
 * The administration dashboard: accounts, failed attempts and daily sign-ins over a period.
 */
class SessionDashboardController extends Controller
{
    public function __invoke(SessionDashboardRequest $request, SessionDashboardQuery $query): View
    {
        $period = $request->period();

        return view('basics13::sessions.dashboard', [
            'period' => $period,
            'periods' => SessionDashboardRequest::PERIODS,
            'summary' => $query->summary($period),
            'inactivityDays' => SessionDashboardQuery::INACTIVITY_DAYS,
        ]);
    }
}
