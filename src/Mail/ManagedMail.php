<?php

namespace Basics13\Mail;

use Illuminate\Mail\Mailable;
use Basics13\Models\EmailTemplate;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;

/**
 * An email made of data instead of a view: a subject and an HTML body, rendered through the given
 * template or, without one, through the default active template. Send it through the mailer that
 * ManagedMailer::prepare() names.
 */
class ManagedMail extends Mailable
{
    private ?EmailTemplate $resolvedTemplate = null;

    private bool $templateResolved = false;

    /**
     * @param  array<string, scalar|null>  $parameters  Values for the `:name:` placeholders
     * @param  list<string>  $attachmentPaths  Files attached as they are
     */
    public function __construct(
        public readonly string $subjectLine,
        public readonly string $htmlBody,
        public readonly array $parameters = [],
        public readonly ?EmailTemplate $template = null,
        public readonly array $attachmentPaths = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: (new EmailTemplateRenderer)->subject($this->chosenTemplate(), $this->subjectLine, $this->parameters, app()->getLocale()),
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: (new EmailTemplateRenderer)->body($this->chosenTemplate(), $this->htmlBody, $this->parameters, app()->getLocale()),
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return array_map(Attachment::fromPath(...), $this->attachmentPaths);
    }

    /**
     * The template given, or the default active one, looked up once for both subject and body.
     */
    private function chosenTemplate(): ?EmailTemplate
    {
        if (! $this->templateResolved) {
            $this->resolvedTemplate = $this->template ?? ManagedMailer::defaultTemplate();
            $this->templateResolved = true;
        }

        return $this->resolvedTemplate;
    }
}
