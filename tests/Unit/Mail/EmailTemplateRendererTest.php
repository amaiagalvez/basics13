<?php

namespace Basics13\Tests\Unit\Mail;

use Basics13\Tests\TestCase;
use Basics13\Models\EmailTemplate;
use Basics13\Mail\EmailTemplateRenderer;

class EmailTemplateRendererTest extends TestCase
{
    public function test_parameters_are_replaced_and_escaped_only_in_the_body(): void
    {
        $renderer = new EmailTemplateRenderer;

        $this->assertSame('Hi A&B', $renderer->subject(null, 'Hi :who:', ['who' => 'A&B'], 'en'));
        $this->assertSame('<p>Hi A&amp;B :unknown:</p>', $renderer->body(null, '<p>Hi :who: :unknown:</p>', ['who' => 'A&B'], 'en'));
    }

    public function test_a_parameter_value_is_not_expanded_again(): void
    {
        $template = new EmailTemplate(['subject_en' => 'S', 'body_en' => ':x: / :content:']);

        $this->assertStringContainsString(
            ':content: / BODY',
            (new EmailTemplateRenderer)->body($template, 'BODY', ['x' => ':content:'], 'en'),
        );
    }

    public function test_the_language_falls_back_to_english_when_blank_or_unknown(): void
    {
        $template = new EmailTemplate(['body_en' => 'English', 'body_es' => 'Español', 'body_eu' => '  ']);
        $renderer = new EmailTemplateRenderer;

        $this->assertSame('Español', $renderer->localized($template, 'body', 'es'));
        $this->assertSame('English', $renderer->localized($template, 'body', 'eu'));
        $this->assertSame('English', $renderer->localized($template, 'body', 'fr'));
        $this->assertSame('English', $renderer->localized($template, 'body', 'de'));
    }

    public function test_the_banner_and_the_legal_text_frame_the_body_only_when_present(): void
    {
        $renderer = new EmailTemplateRenderer;

        $bare = $renderer->body(new EmailTemplate(['body_en' => ':content:']), 'X', [], 'en');
        $this->assertSame('<div>X</div>', $bare);

        $framed = $renderer->body(new EmailTemplate(['body_en' => ':content:', 'banner' => 'https://a.test/b.png', 'legal_text' => 'Legal']), 'X', [], 'en');
        $this->assertStringStartsWith('<p><img src="https://a.test/b.png"', $framed);
        $this->assertStringEndsWith('Legal</div>', $framed);
    }
}
