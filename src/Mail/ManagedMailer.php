<?php

namespace Basics13\Mail;

use Basics13\Models\EmailTemplate;
use Illuminate\Support\Facades\Mail;
use Basics13\Models\EmailConfiguration;

/**
 * The `managed` mailer: SMTP built from a row of BAS_email_configurations instead of `.env`.
 *
 * Laravel keeps every mailer it resolved for the rest of the process, so a row changed after the
 * first send would never be read again. prepare() writes the row into the mailer's config and
 * forgets the resolved instance, which makes the next resolution build its transport anew. Senders
 * call it and hand the name it returns to Mail::mailer(): null when there is no row, which is the
 * application's global mailer.
 */
final class ManagedMailer
{
    public const NAME = 'managed';

    /**
     * Whether a default, active configuration exists for the managed mailer to send through.
     */
    public static function has(): bool
    {
        return self::defaultConfiguration() !== null;
    }

    /**
     * Point the managed mailer at the given configuration, or at the default one, and return its
     * name; null, the global mailer, when there is none.
     */
    public static function prepare(?EmailConfiguration $configuration = null): ?string
    {
        $configuration ??= self::defaultConfiguration();

        if ($configuration === null) {
            return null;
        }

        config(['mail.mailers.'.self::NAME => self::mailerConfig($configuration)]);
        Mail::purge(self::NAME);

        return self::NAME;
    }

    /**
     * The mailer config of a row: SMTP with its server and credentials, and its sender as `from`.
     * `ssl` is implicit TLS (smtps); `tls` lets the transport upgrade with STARTTLS, which `none`
     * turns off.
     *
     * @return array<string, mixed>
     */
    public static function mailerConfig(EmailConfiguration $configuration): array
    {
        return [
            'transport' => self::NAME,
            'scheme' => $configuration->encryption === 'ssl' ? 'smtps' : 'smtp',
            'host' => $configuration->host,
            'port' => $configuration->port,
            'username' => $configuration->username,
            'password' => $configuration->password,
            'auto_tls' => $configuration->encryption !== 'none',
            'timeout' => 10,
            'from' => [
                'address' => $configuration->sender_email,
                'name' => $configuration->sender_name,
            ],
        ];
    }

    public static function defaultConfiguration(): ?EmailConfiguration
    {
        return EmailConfiguration::query()->where('active', true)->where('default', true)->first();
    }

    public static function defaultTemplate(): ?EmailTemplate
    {
        return EmailTemplate::query()->where('active', true)->where('default', true)->first();
    }
}
