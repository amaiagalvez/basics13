<?php

namespace Basics13\Tests\Feature\Email;

use Basics13\Mail\ManagedMail;
use Basics13\Models\EmailTemplate;
use Basics13\Tests\Feature\FeatureTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ManagedMailRenderingTest extends FeatureTestCase
{
    use RefreshDatabase;

    public function test_a_language_without_a_body_falls_back_to_english_with_the_parameters_and_the_frame(): void
    {
        EmailTemplate::factory()->asDefault()->create([
            'banner' => 'https://example.test/banner.png',
            'legal_text' => 'Lege-oharra',
            'subject_en' => ':subject: - App',
            'body_en' => '<h1>Hello :user:</h1>:content:',
        ]);
        app()->setLocale('eu');

        $mail = new ManagedMail('Kaixo', '<p>Gorputza</p>', ['user' => 'Ane <b>']);

        $mail->assertHasSubject('Kaixo - App');
        $mail->assertSeeInOrderInHtml([
            '<img src="https://example.test/banner.png"',
            '<h1>Hello Ane &lt;b&gt;</h1><p>Gorputza</p>',
            'Lege-oharra',
        ], escape: false);
    }

    public function test_a_language_with_its_own_body_uses_it(): void
    {
        $template = EmailTemplate::factory()->create(['body_en' => 'English', 'body_eu' => 'Euskaraz', 'subject_eu' => 'Gaia']);
        app()->setLocale('eu');

        $mail = new ManagedMail('Ez', 'Ez', template: $template);

        $mail->assertHasSubject('Gaia');
        $mail->assertSeeInHtml('Euskaraz');
        $mail->assertDontSeeInHtml('English');
        $mail->assertDontSeeInHtml('<img', escape: false);
    }

    public function test_without_a_default_active_template_the_email_goes_out_unwrapped(): void
    {
        EmailTemplate::factory()->archived()->create(['body_en' => 'Framed :content:']);

        $mail = new ManagedMail('Gaia :n:', '<p>Body :n:</p>', ['n' => 7]);

        $mail->assertHasSubject('Gaia 7');
        $this->assertSame('<p>Body 7</p>', $mail->render());
    }
}
