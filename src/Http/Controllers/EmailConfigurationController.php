<?php

namespace Basics13\Http\Controllers;

use Illuminate\View\View;
use Basics13\Mail\ManagedMail;
use Basics13\Mail\ManagedMailer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\RedirectResponse;
use Basics13\Models\EmailConfiguration;
use Illuminate\Database\QueryException;
use Basics13\Http\Requests\SendTestEmailRequest;
use Basics13\Http\Requests\EmailConfigurationRequest;
use Basics13\Support\Database\UniqueConstraintViolation;
use Basics13\Http\Requests\EmailConfigurationListRequest;
use Basics13\Transformers\EmailConfigurationListTransformer;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Basics13\Queries\EmailConfigurations\EmailConfigurationListQuery;

class EmailConfigurationController extends Controller
{
    public function index(
        EmailConfigurationListRequest $request,
        EmailConfigurationListQuery $query,
        EmailConfigurationListTransformer $transformer,
    ): View|string {
        $search = $request->search();
        $emailConfigurations = $query->active($search);

        return $this->listView($request, 'basics13::email-configurations.list', [
            'emailConfigurations' => $emailConfigurations,
            'list' => $transformer->active(
                $emailConfigurations,
                $search,
                $query->stateCounts(activeTotal: $search === '' ? $emailConfigurations->total() : null),
            ),
            'scope' => 'active',
        ]);
    }

    public function store(EmailConfigurationRequest $request, EmailConfigurationListQuery $query): RedirectResponse|JsonResponse
    {
        $trashed = $query->findTrashedByName($request->string('name')->toString());

        if ($trashed !== null && ! $request->boolean('reuse_deleted_name')) {
            return $this->deletedNameConflict($request, $trashed, 'email-configurations.index', 'deleted_email_configuration_conflict');
        }

        try {
            DB::transaction(function () use ($request): void {
                $this->writeDefault(EmailConfiguration::create($request->configurationAttributes()), $request->marksDefault());
            });
        } catch (QueryException $exception) {
            UniqueConstraintViolation::rethrowAsValidationError($exception, 'name');
        }

        return $this->backToList('email-configurations.index')->with('status', __('basics13::messages.created'));
    }

    public function update(EmailConfigurationRequest $request, EmailConfiguration $emailConfiguration): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $emailConfiguration): void {
                $emailConfiguration->update($request->configurationAttributes());
                $this->writeDefault($emailConfiguration, $request->marksDefault());
            });
        } catch (QueryException $exception) {
            UniqueConstraintViolation::rethrowAsValidationError($exception, 'name');
        }

        return $this->backToList('email-configurations.index')->with('status', __('basics13::messages.updated'));
    }

    /**
     * The default row keeps its flag in the trash, so restoring it brings it back; until then
     * nothing reads it as the default.
     */
    public function destroy(EmailConfiguration $emailConfiguration): RedirectResponse
    {
        $this->authorize('delete', $emailConfiguration);

        $emailConfiguration->delete();

        return $this->backToList('email-configurations.index')->with('status', __('basics13::messages.moved_to_trash'));
    }

    /**
     * Sent at once, not queued: it is one email to someone waiting for the result on the screen.
     */
    public function sendTest(SendTestEmailRequest $request, EmailConfiguration $emailConfiguration): RedirectResponse
    {
        try {
            Mail::mailer(ManagedMailer::prepare($emailConfiguration))
                ->to($request->recipient())
                ->send($this->testMail($emailConfiguration));
        } catch (TransportExceptionInterface $exception) {
            return $this->backToList('email-configurations.index')
                ->with('error', __('The test email could not be sent: :reason', ['reason' => $exception->getMessage()]));
        }

        return $this->backToList('email-configurations.index')
            ->with('status', __('The test email was sent to :recipient.', ['recipient' => $request->recipient()]));
    }

    private function writeDefault(EmailConfiguration $emailConfiguration, bool $marksDefault): void
    {
        if ($marksDefault) {
            $emailConfiguration->markAsDefault();
        } elseif ($emailConfiguration->default) {
            $emailConfiguration->forceFill(['default' => false])->save();
        }
    }

    /**
     * Sent through the configuration under test, whether or not it is the default one.
     */
    private function testMail(EmailConfiguration $emailConfiguration): ManagedMail
    {
        return new ManagedMail(
            __('Test email'),
            '<p>'.e(__('This test email was sent through the SMTP configuration :name.', ['name' => $emailConfiguration->name])).'</p>',
        );
    }
}
