<?php

namespace Basics13\Database\Seeders;

use Illuminate\Database\Seeder;
use Basics13\Models\EmailTemplate;

/**
 * Three ready-made layouts every application starts with: `:content:` and `:subject:` are filled in
 * by each email. The markup is what email clients render alike (Gmail, Outlook, Apple Mail, Yahoo):
 * nested tables, inline styles, system fonts and a 600px column that shrinks on narrow screens
 * without media queries, plus a fixed-width ghost table for desktop Outlook.
 *
 * `:banner:` and `:legal_text:` mark where the template's banner and legal text go, so both stay
 * inside the layout and disappear when the template has none. Rows that already exist by name are not touched, so editing a template survives reseeding.
 */
class EmailTemplateSeeder extends Seeder
{
    private const FONT = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";

    /**
     * The footer line of every layout, per language.
     *
     * @var array<string, string>
     */
    private const FOOTER = [
        'eu' => 'Mezu hau automatikoki bidali da. Mesedez, ez erantzun.',
        'es' => 'Este mensaje se ha enviado automáticamente. Por favor, no respondas.',
        'fr' => 'Ce message a été envoyé automatiquement. Merci de ne pas y répondre.',
        'en' => 'This message was sent automatically. Please do not reply.',
    ];

    public function run(): void
    {
        $templates = [
            'Básica' => fn (string $locale): string => $this->basic($locale),
            'Corporativa' => fn (string $locale): string => $this->corporate($locale),
            'Aviso' => fn (string $locale): string => $this->notice($locale),
        ];

        foreach ($templates as $name => $body) {
            $attributes = [];

            foreach (EmailTemplate::LOCALES as $locale) {
                $attributes['subject_'.$locale] = ':subject:';
                $attributes['body_'.$locale] = $body($locale);
            }

            EmailTemplate::query()->firstOrCreate(['name' => $name], $attributes);
        }
    }

    /**
     * White card on a light grey page.
     */
    private function basic(string $locale): string
    {
        return $this->layout(
            page: '#f4f4f5',
            header: '',
            card: '<td style="padding:32px 32px 24px 32px;font-family:'.self::FONT.';font-size:16px;line-height:24px;color:#18181b;">:content:</td>',
            locale: $locale,
        );
    }

    /**
     * Coloured band with the application name above the card.
     */
    private function corporate(string $locale): string
    {
        $header = '<tr><td bgcolor="#1e3a8a" style="background-color:#1e3a8a;padding:24px 32px;border-radius:8px 8px 0 0;font-family:'.self::FONT.';font-size:20px;line-height:28px;font-weight:bold;color:#ffffff;">'
            .e((string) config('app.name')).'</td></tr>';

        return $this->layout(
            page: '#e5e7eb',
            header: $header,
            card: '<td style="padding:32px;font-family:'.self::FONT.';font-size:16px;line-height:24px;color:#1f2937;">'
                .'<h1 style="margin:0 0 16px 0;font-size:22px;line-height:30px;color:#111827;">:subject:</h1>:content:</td>',
            locale: $locale,
            cardRadius: '0 0 8px 8px',
        );
    }

    /**
     * Alert: a warning stripe on top and the subject as heading, for system notices.
     */
    private function notice(string $locale): string
    {
        $header = '<tr><td bgcolor="#f59e0b" height="6" style="background-color:#f59e0b;height:6px;font-size:0;line-height:0;border-radius:8px 8px 0 0;">&nbsp;</td></tr>';

        return $this->layout(
            page: '#fafaf9',
            header: $header,
            card: '<td style="padding:28px 32px;font-family:'.self::FONT.';font-size:16px;line-height:24px;color:#1c1917;">'
                .'<p style="margin:0 0 12px 0;font-size:18px;line-height:26px;font-weight:bold;color:#b45309;">&#9888;&nbsp;:subject:</p>:content:</td>',
            locale: $locale,
            cardRadius: '0 0 8px 8px',
        );
    }

    /**
     * The shared frame: full-width page table, centred fluid column (ghost table for Outlook), the
     * card and the footer.
     */
    private function layout(string $page, string $header, string $card, string $locale, string $cardRadius = '8px'): string
    {
        $footer = e(self::FOOTER[$locale]).'<br>&copy; '.e((string) config('app.name'));

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="'.$page.'" style="width:100%;background-color:'.$page.';margin:0;padding:0;">'
            .'<tr><td align="center" style="padding:24px 12px;">'
            .'<!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" align="center"><tr><td><![endif]-->'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;margin:0 auto;">'
            .'<tr><td>:banner:</td></tr>'
            .$header
            .'<tr>'.str_replace('<td style="', '<td bgcolor="#ffffff" style="background-color:#ffffff;border-radius:'.$cardRadius.';', $card).'</tr>'
            .'<tr><td align="center" style="padding:20px 16px;font-family:'.self::FONT.';font-size:12px;line-height:18px;color:#71717a;">:legal_text:<p style="margin:12px 0 0 0;">'.$footer.'</p></td></tr>'
            .'</table>'
            .'<!--[if mso]></td></tr></table><![endif]-->'
            .'</td></tr></table>';
    }
}
