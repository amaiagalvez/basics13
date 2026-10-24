<?php

namespace Basics13\Http\Controllers;

use Illuminate\View\View;
use Basics13\Mail\ManagedMail;
use Basics13\Mail\ManagedMailer;
use Illuminate\Http\JsonResponse;
use Basics13\Models\EmailTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\QueryException;
use Basics13\Http\Requests\EmailTemplateRequest;
use Basics13\Http\Requests\SendTestEmailRequest;
use Basics13\Http\Requests\EmailTemplateListRequest;
use Basics13\Transformers\EmailTemplateListTransformer;
use Basics13\Support\Database\UniqueConstraintViolation;
use Basics13\Queries\EmailTemplates\EmailTemplateListQuery;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class EmailTemplateController extends Controller
{
    public function index(
        EmailTemplateListRequest $request,
        EmailTemplateListQuery $query,
        EmailTemplateListTransformer $transformer,
    ): View|string {
        $search = $request->search();
        $emailTemplates = $query->active($search);

        return $this->listView($request, 'basics13::email-templates.list', [
            'emailTemplates' => $emailTemplates,
            'list' => $transformer->active(
                $emailTemplates,
                $search,
                $query->stateCounts(activeTotal: $search === '' ? $emailTemplates->total() : null),
            ),
            'scope' => 'active',
        ]);
    }

    public function store(EmailTemplateRequest $request, EmailTemplateListQuery $query): RedirectResponse|JsonResponse
    {
        $trashed = $query->findTrashedByName($request->string('name')->toString());

        if ($trashed !== null && ! $request->boolean('reuse_deleted_name')) {
            return $this->deletedNameConflict($request, $trashed, 'email-templates.index', 'deleted_email_template_conflict');
        }

        try {
            DB::transaction(function () use ($request): void {
                $this->writeDefault(EmailTemplate::create($request->templateAttributes()), $request->marksDefault());
            });
        } catch (QueryException $exception) {
            UniqueConstraintViolation::rethrowAsValidationError($exception, 'name');
        }

        return $this->backToList('email-templates.index')->with('status', __('basics13::messages.created'));
    }

    public function update(EmailTemplateRequest $request, EmailTemplate $emailTemplate): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $emailTemplate): void {
                $emailTemplate->update($request->templateAttributes());
                $this->writeDefault($emailTemplate, $request->marksDefault());
            });
        } catch (QueryException $exception) {
            UniqueConstraintViolation::rethrowAsValidationError($exception, 'name');
        }

        return $this->backToList('email-templates.index')->with('status', __('basics13::messages.updated'));
    }

    /**
     * The default row keeps its flag in the trash, so restoring it brings it back; until then
     * nothing reads it as the default.
     */
    public function destroy(EmailTemplate $emailTemplate): RedirectResponse
    {
        $this->authorize('delete', $emailTemplate);

        $emailTemplate->delete();

        return $this->backToList('email-templates.index')->with('status', __('basics13::messages.moved_to_trash'));
    }

    /**
     * Sent at once, not queued: it is one email to someone waiting for the result on the screen.
     */
    public function sendTest(SendTestEmailRequest $request, EmailTemplate $emailTemplate): RedirectResponse
    {
        try {
            Mail::mailer(ManagedMailer::prepare())
                ->to($request->recipient())
                ->send($this->testMail($emailTemplate));
        } catch (TransportExceptionInterface $exception) {
            return $this->backToList('email-templates.index')
                ->with('error', __('The test email could not be sent: :reason', ['reason' => $exception->getMessage()]));
        }

        return $this->backToList('email-templates.index')
            ->with('status', __('The test email was sent to :recipient.', ['recipient' => $request->recipient()]));
    }

    private function writeDefault(EmailTemplate $emailTemplate, bool $marksDefault): void
    {
        if ($marksDefault) {
            $emailTemplate->markAsDefault();
        } elseif ($emailTemplate->default) {
            $emailTemplate->forceFill(['default' => false])->save();
        }
    }

    /**
     * Sent through the managed mailer, or the global one when no SMTP configuration is the default.
     */
    private function testMail(EmailTemplate $emailTemplate): ManagedMail
    {
        return new ManagedMail(
            __('Test email'),
            '<p>'.e(__('This test email shows the template :name.', ['name' => $emailTemplate->name])).'</p>',
            template: $emailTemplate,
        );
    }
}
