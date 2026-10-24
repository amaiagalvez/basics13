<?php

namespace Basics13\Http\Controllers;

use Illuminate\View\View;
use Basics13\Models\EmailTemplate;
use Illuminate\Http\RedirectResponse;
use Basics13\Http\Requests\EmailTemplateListRequest;
use Basics13\Transformers\EmailTemplateListTransformer;
use Basics13\Queries\EmailTemplates\EmailTemplateListQuery;

class EmailTemplateArchivedController extends ArchivedController
{
    public function index(
        EmailTemplateListRequest $request,
        EmailTemplateListQuery $query,
        EmailTemplateListTransformer $transformer,
    ): View|string {
        $search = $request->search();
        $emailTemplates = $query->archived($search);

        return $this->listView($request, 'basics13::email-templates.list', [
            'emailTemplates' => $emailTemplates,
            'list' => $transformer->archived(
                $emailTemplates,
                $search,
                $query->stateCounts(archivedTotal: $search === '' ? $emailTemplates->total() : null),
            ),
            'scope' => 'archived',
        ]);
    }

    /**
     * Archiving the default row takes its flag away: the model clears it with the active flag.
     */
    public function archive(EmailTemplate $emailTemplate): RedirectResponse
    {
        return $this->archiveRecord($emailTemplate);
    }

    public function activate(EmailTemplate $emailTemplate): RedirectResponse
    {
        return $this->activateRecord($emailTemplate);
    }

    protected function activeRoute(): string
    {
        return 'email-templates.index';
    }

    protected function archivedRoute(): string
    {
        return 'email-templates.archived.index';
    }
}
