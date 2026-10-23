<?php

namespace Basics13\Http\Controllers;

use Illuminate\View\View;
use Illuminate\Database\Eloquent\Model;
use Basics13\Queries\Users\PermissionSummaryQuery;
use Basics13\Http\Requests\PermissionSummaryRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Basics13\Transformers\PermissionSummaryTransformer;

/**
 * The permissions each account holds through its roles, on screen and as a CSV of the very rows the
 * search and the filters leave.
 */
class PermissionSummaryController extends Controller
{
    public function index(
        PermissionSummaryRequest $request,
        PermissionSummaryQuery $query,
        PermissionSummaryTransformer $transformer,
    ): View|string {
        $search = $request->search();
        $filters = $request->filters() ?? [];
        $users = $query->visibleTo($request->user())->page($search, $filters);
        $modules = $query->modules();

        return $this->listView($request, 'basics13::users.permissions-summary', [
            'users' => $users,
            'rows' => collect($users->items())->map(static fn (Model $user): array => $transformer->row($user, $modules))->all(),
            'modules' => $transformer->moduleLabels($modules),
            'search' => $search,
            'searchableFields' => $query->searchableFields('active'),
            'filters' => $filters,
            'exportUrl' => route('users.permissions-summary.export', array_filter([
                'search' => $search,
                'filters' => $filters,
            ])),
        ]);
    }

    public function export(
        PermissionSummaryRequest $request,
        PermissionSummaryQuery $query,
        PermissionSummaryTransformer $transformer,
    ): StreamedResponse {
        $search = $request->search();
        $filters = $request->filters() ?? [];
        $modules = $query->modules();
        $query->visibleTo($request->user());

        return response()->streamDownload(static function () use ($query, $transformer, $search, $filters, $modules): void {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                return;
            }

            fputcsv($output, $transformer->csvHeader($modules), escape: '');

            foreach ($query->all($search, $filters) as $user) {
                fputcsv($output, $transformer->csvLine($transformer->row($user, $modules)), escape: '');
            }

            fclose($output);
        }, 'permissions-summary.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
