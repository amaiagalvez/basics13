<?php

namespace Basics13\Http\Controllers;

use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Basics13\Models\EmailConfiguration;
use Illuminate\Database\Eloquent\Model;
use Basics13\Http\Requests\EmailConfigurationListRequest;
use Basics13\Http\Requests\EmailConfigurationDestroyRequest;
use Basics13\Http\Requests\EmailConfigurationRestoreRequest;
use Basics13\Transformers\EmailConfigurationListTransformer;
use Basics13\Queries\EmailConfigurations\EmailConfigurationListQuery;

/**
 * @extends TrashController<EmailConfiguration>
 */
class EmailConfigurationTrashController extends TrashController
{
    public function index(
        EmailConfigurationListRequest $request,
        EmailConfigurationListQuery $query,
        EmailConfigurationListTransformer $transformer,
    ): View|string {
        $search = $request->search();
        $emailConfigurations = $query->trashed($search);

        return $this->listView($request, 'basics13::email-configurations.list', [
            'emailConfigurations' => $emailConfigurations,
            'list' => $transformer->trash(
                $emailConfigurations,
                $search,
                $query->stateCounts(trashedTotal: $search === '' ? $emailConfigurations->total() : null),
            ),
            'scope' => 'trashed',
        ]);
    }

    public function restore(EmailConfigurationRestoreRequest $request): RedirectResponse
    {
        return $this->restoreTrashed($request);
    }

    public function destroy(EmailConfigurationDestroyRequest $request): RedirectResponse
    {
        return $this->destroyTrashed($request);
    }

    /**
     * A row that was the default comes back as the default and takes the flag from the other rows,
     * all in one transaction.
     *
     * @param  EmailConfiguration  $record
     */
    protected function restoreTrashedRecord(Model $record): void
    {
        DB::transaction(static fn () => $record->restore());
    }

    /**
     * @param  EmailConfiguration  $record
     */
    protected function nameIsTaken(Model $record): bool
    {
        return $this->takenBy(EmailConfiguration::query(), $record->name);
    }

    protected function recordClass(): string
    {
        return EmailConfiguration::class;
    }

    protected function trashRoute(): string
    {
        return 'email-configurations.trash.index';
    }
}
