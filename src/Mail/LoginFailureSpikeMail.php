<?php

namespace Basics13\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The alert sent when failed sign-ins spike. Counts and a link only: never a login or an IP, so
 * whoever the address belongs to learns nothing about the accounts under attack.
 */
class LoginFailureSpikeMail extends Mailable
{
    public function __construct(
        public readonly int $failures,
        public readonly int $logins,
        public readonly int $ips,
        public readonly int $minutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Failed sign-ins are spiking'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'basics13::mail.login-failure-spike',
            with: ['url' => route('sessions.index')],
        );
    }
}
