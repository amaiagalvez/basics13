<?php

namespace Basics13\Http\Controllers;

use Illuminate\View\View;
use Basics13\Models\SessionLog;
use Basics13\Http\Requests\SessionLogListRequest;
use Basics13\Queries\Sessions\SessionLogListQuery;
use Basics13\Transformers\SessionLogListTransformer;

/**
 * The access register, newest first. It is only listed: the login audit is its one writer.
 */
class SessionLogController extends Controller
{
    public function index(
        SessionLogListRequest $request,
        SessionLogListQuery $query,
        SessionLogListTransformer $transformer,
    ): View|string {
        $search = $request->search();
        $filters = $request->filters() ?? [];
        $sessions = $query->page($search, $filters);

        return $this->listView($request, 'basics13::sessions.list', [
            'sessions' => $sessions,
            'rows' => collect($sessions->items())->map(static fn (SessionLog $session): array => $transformer->row($session))->all(),
            'search' => $search,
            'searchableFields' => $query->searchableFields('active'),
            'filters' => $filters,
        ]);
    }
}
