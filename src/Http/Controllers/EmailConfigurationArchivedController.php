<?php

namespace Basics13\Http\Controllers;

use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Basics13\Models\EmailConfiguration;
use Basics13\Http\Requests\EmailConfigurationListRequest;
use Basics13\Transformers\EmailConfigurationListTransformer;
use Basics13\Queries\EmailConfigurations\EmailConfigurationListQuery;

class EmailConfigurationArchivedController extends ArchivedController
{
    public function index(
        EmailConfigurationListRequest $request,
        EmailConfigurationListQuery $query,
        EmailConfigurationListTransformer $transformer,
    ): View|string {
        $search = $request->search();
        $emailConfigurations = $query->archived($search);

        return $this->listView($request, 'basics13::email-configurations.list', [
            'emailConfigurations' => $emailConfigurations,
            'list' => $transformer->archived(
                $emailConfigurations,
                $search,
                $query->stateCounts(archivedTotal: $search === '' ? $emailConfigurations->total() : null),
            ),
            'scope' => 'archived',
        ]);
    }

    /**
     * Archiving the default row takes its flag away: the model clears it with the active flag.
     */
    public function archive(EmailConfiguration $emailConfiguration): RedirectResponse
    {
        return $this->archiveRecord($emailConfiguration);
    }

    public function activate(EmailConfiguration $emailConfiguration): RedirectResponse
    {
        return $this->activateRecord($emailConfiguration);
    }

    protected function activeRoute(): string
    {
        return 'email-configurations.index';
    }

    protected function archivedRoute(): string
    {
        return 'email-configurations.archived.index';
    }
}
