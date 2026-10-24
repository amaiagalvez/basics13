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
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

class ManagedDefaultAndMailerTest extends UserTestCase
{
    use RefreshDatabase;

    public function test_marking_a_second_configuration_leaves_a_single_default(): void
    {
        $this->actingAs($this->editor());
        $first = EmailConfiguration::factory()->asDefault()->create();
        $second = EmailConfiguration::factory()->create();

        $this->put(route('email-configurations.update', $second), [
            ...$second->only(['name', 'sender_name', 'sender_email', 'host', 'port', 'encryption']),
            'default' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertFalse($first->refresh()->default);
        $this->assertTrue($second->refresh()->default);
        $this->assertSame(1, EmailConfiguration::query()->where('default', true)->count());
    }

    public function test_marking_an_archived_row_as_default_activates_it(): void
    {
        $template = EmailTemplate::factory()->archived()->create();

        $template->markAsDefault();

        $this->assertTrue($template->refresh()->active);
        $this->assertTrue($template->default);
    }

    public function test_archiving_the_default_row_takes_its_flag_away(): void
    {
        $this->actingAs($this->editor());
        $configuration = EmailConfiguration::factory()->asDefault()->create();

        $this->patch(route('email-configurations.archive', $configuration));

        $this->assertFalse($configuration->refresh()->default);
        $this->assertFalse(ManagedMailer::has());
    }

    public function test_the_trashed_default_stops_being_used_and_gets_its_flag_back_on_restore(): void
    {
        $this->actingAs($this->editor());
        $default = EmailConfiguration::factory()->asDefault()->create();
        $other = EmailConfiguration::factory()->create();

        $default->delete();
        $this->assertFalse(ManagedMailer::has());

        // Something else became the default meanwhile, without going through markAsDefault().
        $other->forceFill(['default' => true])->save();

        $this->patch(route('email-configurations.trash.restore', $default->id))->assertSessionHasNoErrors();

        $this->assertTrue($default->refresh()->default);
        $this->assertFalse($other->refresh()->default);
        $this->assertTrue(ManagedMailer::defaultConfiguration()?->is($default));
    }

    public function test_the_default_row_is_not_deleted_permanently(): void
    {
        $this->actingAs($this->editor());
        $default = EmailTemplate::factory()->asDefault()->trashed()->create();

        $this->get(route('email-templates.trash.index'))
            ->assertOk()
            ->assertSee('email-template-force-delete-blocked-'.$default->id);

        $this->delete(route('email-templates.trash.destroy', $default->id));
        $this->assertDatabaseHas('BAS_email_templates', ['id' => $default->id]);

        EmailTemplate::factory()->create()->markAsDefault();

        $this->delete(route('email-templates.trash.destroy', $default->id))
            ->assertSessionHas('status', __('basics13::messages.permanently_deleted'));
        $this->assertDatabaseMissing('BAS_email_templates', ['id' => $default->id]);
    }

    public function test_without_a_default_active_configuration_the_global_mailer_sends(): void
    {
        Mail::fake();
        EmailConfiguration::factory()->create();

        $this->assertNull(ManagedMailer::prepare());
        Mail::mailer(ManagedMailer::prepare())->to('a@example.com')->send(new ManagedMail('Kaixo', '<p>Kaixo</p>'));

        Mail::assertSent(ManagedMail::class, static fn (ManagedMail $mail): bool => $mail->mailer === null);
    }

    public function test_with_a_default_configuration_the_managed_mailer_sends(): void
    {
        Mail::fake();
        EmailConfiguration::factory()->asDefault()->create();

        Mail::mailer(ManagedMailer::prepare())->to('a@example.com')->send(new ManagedMail('Kaixo', '<p>Kaixo</p>'));

        Mail::assertSent(ManagedMail::class, static fn (ManagedMail $mail): bool => $mail->mailer === ManagedMailer::NAME);
    }

    public function test_changing_the_default_rebuilds_the_transport_on_the_next_send(): void
    {
        $first = EmailConfiguration::factory()->asDefault()->create(['host' => 'first.example.test', 'port' => 2525, 'sender_email' => 'one@example.test']);
        ManagedMailer::prepare();
        $this->assertSame('first.example.test', $this->managedStream()->getHost());

        $second = EmailConfiguration::factory()->create(['host' => 'second.example.test', 'port' => 465, 'encryption' => 'ssl', 'sender_email' => 'two@example.test']);
        $second->markAsDefault();
        ManagedMailer::prepare();

        $stream = $this->managedStream();
        $this->assertSame('second.example.test', $stream->getHost());
        $this->assertSame(465, $stream->getPort());
        $this->assertTrue($stream->isTLS());
        $this->assertFalse($first->refresh()->default);
    }

    public function test_the_managed_mailer_resolved_by_name_reads_the_default_row_or_falls_back(): void
    {
        $this->assertNotInstanceOf(EsmtpTransport::class, Mail::mailer(ManagedMailer::NAME)->getSymfonyTransport());

        EmailConfiguration::factory()->asDefault()->create(['host' => 'direct.example.test']);
        Mail::purge(ManagedMailer::NAME);

        $this->assertSame('direct.example.test', $this->managedStream()->getHost());
    }

    private function managedStream(): SocketStream
    {
        $transport = Mail::mailer(ManagedMailer::NAME)->getSymfonyTransport();
        $this->assertInstanceOf(EsmtpTransport::class, $transport);
        $stream = $transport->getStream();
        $this->assertInstanceOf(SocketStream::class, $stream);

        return $stream;
    }

    private function editor(): User
    {
        return User::factory()->withPermissions(['email.view', 'email.edit'])->create();
    }
}
