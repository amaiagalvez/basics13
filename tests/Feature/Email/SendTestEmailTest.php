<?php

namespace Basics13\Tests\Feature\Email;

use Basics13\Models\User;
use Basics13\Mail\ManagedMail;
use Basics13\Mail\ManagedMailer;
use Basics13\Models\EmailTemplate;
use Illuminate\Support\Facades\Mail;
use Basics13\Models\EmailConfiguration;
use Basics13\Tests\Feature\Users\UserTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SendTestEmailTest extends UserTestCase
{
    use RefreshDatabase;

    public function test_a_configuration_test_is_sent_through_that_configuration(): void
    {
        Mail::fake();
        $this->actingAs($this->editor());
        $configuration = EmailConfiguration::factory()->create(['sender_email' => 'from@example.test']);

        $this->post(route('email-configurations.send-test', $configuration), ['recipient' => 'to@example.test'])
            ->assertRedirect(route('email-configurations.index'))
            ->assertSessionHas('status', __('The test email was sent to :recipient.', ['recipient' => 'to@example.test']));

        Mail::assertSent(ManagedMail::class, static fn (ManagedMail $mail): bool => $mail->hasTo('to@example.test')
            && $mail->mailer === ManagedMailer::NAME);
        $this->assertSame('from@example.test', config('mail.mailers.managed.from.address'));
    }

    public function test_the_recipient_is_validated(): void
    {
        Mail::fake();
        $this->actingAs($this->editor());
        $configuration = EmailConfiguration::factory()->create();

        $this->post(route('email-configurations.send-test', $configuration), ['recipient' => 'nope'])
            ->assertSessionHasErrors('recipient');

        Mail::assertNothingSent();
    }

    public function test_an_unreachable_server_is_reported_as_a_failure(): void
    {
        $this->actingAs($this->editor());
        $configuration = EmailConfiguration::factory()->create(['host' => '127.0.0.1', 'port' => 1, 'encryption' => 'none']);

        $this->post(route('email-configurations.send-test', $configuration), ['recipient' => 'to@example.test'])
            ->assertRedirect(route('email-configurations.index'))
            ->assertSessionHas('error', static fn (string $error): bool => str_starts_with($error, __('The test email could not be sent: :reason', ['reason' => ''])));
    }

    public function test_a_template_test_without_a_managed_smtp_goes_out_through_the_global_mailer(): void
    {
        Mail::fake();
        $this->actingAs($this->editor());
        $template = EmailTemplate::factory()->create(['body_en' => 'Template body']);

        $this->post(route('email-templates.send-test', $template), ['recipient' => 'to@example.test'])
            ->assertSessionHas('status', __('The test email was sent to :recipient.', ['recipient' => 'to@example.test']));

        Mail::assertSent(ManagedMail::class, static fn (ManagedMail $mail): bool => $mail->mailer === null
            && $mail->template?->is($template) === true);
    }

    private function editor(): User
    {
        return User::factory()->withPermissions(['email.view', 'email.edit'])->create();
    }
}
