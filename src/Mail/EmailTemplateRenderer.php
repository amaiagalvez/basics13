<?php

namespace Basics13\Mail;

use Basics13\Models\EmailTemplate;

/**
 * Turns a template and the data of one email into its subject and HTML body.
 *
 * Parameters are written `:name:`. Two of them are reserved: `:subject:` and `:content:` stand for
 * the subject and body the sender passed, so a template can either wrap every email (a body made of
 * `:content:` and some framing) or be the whole message. `:banner:` and `:legal_text:` place the
 * template's banner and legal text inside its own layout; a body without them gets both framed
 * around it in an email-safe table. Without a template the sender's subject
 * and body go out alone, with their parameters replaced and nothing around them.
 */
final class EmailTemplateRenderer
{
    /**
     * @param  array<string, scalar|null>  $parameters
     */
    public function subject(?EmailTemplate $template, string $subject, array $parameters, string $locale): string
    {
        $subject = $this->substitute($subject, $parameters, escape: false);

        if ($template === null) {
            return $subject;
        }

        return strtr(
            $this->localized($template, 'subject', $locale),
            [...$this->replacements($parameters, escape: false), ':subject:' => $subject],
        );
    }

    /**
     * Parameter values are escaped, since the body is HTML; the sender's own body is HTML already
     * and goes in as it is.
     *
     * @param  array<string, scalar|null>  $parameters
     */
    public function body(?EmailTemplate $template, string $body, array $parameters, string $locale): string
    {
        $body = $this->substitute($body, $parameters, escape: true);

        if ($template === null) {
            return $body;
        }

        $layout = $this->localized($template, 'body', $locale);
        $placed = str_contains($layout, ':banner:') || str_contains($layout, ':legal_text:');

        // One pass, so a parameter value that happens to contain `:content:` is not expanded.
        $content = strtr($layout, [
            ...$this->replacements($parameters, escape: true),
            ':content:' => $body,
            ':banner:' => $this->banner($template),
            ':legal_text:' => $this->legalText($template),
        ]);

        return $placed ? $content : $this->wrap($template, $content);
    }

    /**
     * The template's text in the given language, or in English when that language has none.
     *
     * @param  'subject'|'body'  $field
     */
    public function localized(EmailTemplate $template, string $field, string $locale): string
    {
        $value = in_array($locale, EmailTemplate::LOCALES, true)
            ? $template->getAttribute($field.'_'.$locale)
            : null;

        if (! is_string($value) || trim($value) === '') {
            $value = $template->getAttribute($field.'_'.EmailTemplate::FALLBACK_LOCALE);
        }

        return (string) $value;
    }

    /**
     * Replace every `:name:` with its value.
     *
     * @param  array<string, scalar|null>  $parameters
     */
    public function substitute(string $text, array $parameters, bool $escape): string
    {
        return strtr($text, $this->replacements($parameters, $escape));
    }

    /**
     * @param  array<string, scalar|null>  $parameters
     * @return array<string, string>
     */
    private function replacements(array $parameters, bool $escape): array
    {
        $replacements = [];

        foreach ($parameters as $name => $value) {
            $value = (string) $value;
            $replacements[':'.$name.':'] = $escape ? e($value) : $value;
        }

        return $replacements;
    }

    /**
     * The banner above the content and the legal text below it, in the single-column table email
     * clients render alike; each one only when the template has it.
     */
    private function wrap(EmailTemplate $template, string $content): string
    {
        $banner = $this->banner($template);
        $legalText = $this->legalText($template);

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;margin:0 auto;">'
            .($banner !== '' ? '<tr><td style="padding:0 0 16px 0;">'.$banner.'</td></tr>' : '')
            .'<tr><td>'.$content.'</td></tr>'
            .($legalText !== '' ? '<tr><td style="padding:16px 0 0 0;border-top:1px solid #e4e4e7;">'.$legalText.'</td></tr>' : '')
            .'</table>';
    }

    private function banner(EmailTemplate $template): string
    {
        if (! is_string($template->banner) || $template->banner === '') {
            return '';
        }

        return '<img src="'.e($template->banner).'" alt="" width="600" style="display:block;width:100%;max-width:600px;height:auto;border:0;outline:none;text-decoration:none;">';
    }

    private function legalText(EmailTemplate $template): string
    {
        if (! is_string($template->legal_text) || trim($template->legal_text) === '') {
            return '';
        }

        return '<div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:18px;color:#71717a;">'.$template->legal_text.'</div>';
    }
}
