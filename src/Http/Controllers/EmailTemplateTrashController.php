<?php

namespace Basics13\Http\Controllers;

use Illuminate\View\View;
use Basics13\Models\EmailTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\Eloquent\Model;
use Basics13\Http\Requests\EmailTemplateListRequest;
use Basics13\Http\Requests\EmailTemplateDestroyRequest;
use Basics13\Http\Requests\EmailTemplateRestoreRequest;
use Basics13\Transformers\EmailTemplateListTransformer;
use Basics13\Queries\EmailTemplates\EmailTemplateListQuery;

/**
 * @extends TrashController<EmailTemplate>
 */
class EmailTemplateTrashController extends TrashController
{
    public function index(
        EmailTemplateListRequest $request,
        EmailTemplateListQuery $query,
        EmailTemplateListTransformer $transformer,
    ): View|string {
        $search = $request->search();
        $emailTemplates = $query->trashed($search);

        return $this->listView($request, 'basics13::email-templates.list', [
            'emailTemplates' => $emailTemplates,
            'list' => $transformer->trash(
                $emailTemplates,
                $search,
                $query->stateCounts(trashedTotal: $search === '' ? $emailTemplates->total() : null),
            ),
            'scope' => 'trashed',
        ]);
    }

    public function restore(EmailTemplateRestoreRequest $request): RedirectResponse
    {
        return $this->restoreTrashed($request);
    }

    public function destroy(EmailTemplateDestroyRequest $request): RedirectResponse
    {
        return $this->destroyTrashed($request);
    }

    /**
     * A row that was the default comes back as the default and takes the flag from the other rows,
     * all in one transaction.
     *
     * @param  EmailTemplate  $record
     */
    protected function restoreTrashedRecord(Model $record): void
    {
        DB::transaction(static fn () => $record->restore());
    }

    /**
     * @param  EmailTemplate  $record
     */
    protected function nameIsTaken(Model $record): bool
    {
        return $this->takenBy(EmailTemplate::query(), $record->name);
    }

    protected function recordClass(): string
    {
        return EmailTemplate::class;
    }

    protected function trashRoute(): string
    {
        return 'email-templates.trash.index';
    }
}
