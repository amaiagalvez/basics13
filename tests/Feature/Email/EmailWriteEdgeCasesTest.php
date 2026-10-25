<?php

namespace Basics13\Tests\Feature\Email;

use PDOException;
use Basics13\Models\User;
use Basics13\Models\EmailTemplate;
use Basics13\Models\EmailConfiguration;
use Illuminate\Database\QueryException;
use Basics13\Tests\Feature\Users\UserTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The branches of the email template and configuration writes the CRUD tests do not reach: the
 * trashed-name conflict, the races on the side the CRUD tests leave out, the default flag and the
 * failed test send of a template.
 */
class EmailWriteEdgeCasesTest extends UserTestCase
{
    use RefreshDatabase;

    public function test_a_template_name_taken_by_a_trashed_template_opens_the_conflict(): void
    {
        $this->actingAs($this->editor());
        $trashed = EmailTemplate::factory()->trashed()->create(['name' => 'Zaharra']);

        $this->post(route('email-templates.store'), $this->templatePayload(['name' => 'Zaharra']))
            ->assertSessionHas('deleted_email_template_conflict', fn (array $payload): bool => $payload['id'] === $trashed->id);

        $this->assertDatabaseCount('BAS_email_templates', 1);
    }

    public function test_a_template_store_race_is_a_name_error(): void
    {
        $this->actingAs($this->editor());
        EmailTemplate::creating(self::duplicateKey(...));

        $this->post(route('email-templates.store'), $this->templatePayload())
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('BAS_email_templates', 0);
    }

    public function test_a_configuration_update_race_is_a_name_error(): void
    {
        $this->actingAs($this->editor());
        $configuration = EmailConfiguration::factory()->create(['name' => 'Jatorrizkoa']);
        EmailConfiguration::updating(self::duplicateKey(...));

        $this->put(route('email-configurations.update', $configuration), $this->configurationPayload(['name' => 'Lehiakidea']))
            ->assertSessionHasErrors('name');

        $this->assertSame('Jatorrizkoa', $configuration->refresh()->name);
    }

    public function test_a_template_can_be_marked_and_unmarked_as_default(): void
    {
        $this->actingAs($this->editor());
        $template = EmailTemplate::factory()->create();

        $this->put(route('email-templates.update', $template), $this->templatePayload(['default' => true]))
            ->assertSessionHasNoErrors();
        $this->assertTrue($template->refresh()->default);

        $this->put(route('email-templates.update', $template), $this->templatePayload())
            ->assertSessionHasNoErrors();
        $this->assertFalse($template->refresh()->default);
    }

    public function test_an_update_without_the_flag_unmarks_the_default_configuration(): void
    {
        $this->actingAs($this->editor());
        $configuration = EmailConfiguration::factory()->asDefault()->create();

        $this->put(route('email-configurations.update', $configuration), $this->configurationPayload())
            ->assertSessionHasNoErrors();

        $this->assertFalse($configuration->refresh()->default);
    }

    public function test_an_archived_template_can_be_activated(): void
    {
        $this->actingAs($this->editor());
        $template = EmailTemplate::factory()->create(['active' => false]);

        $this->patch(route('email-templates.archived.activate', $template))
            ->assertSessionHasNoErrors();

        $this->assertTrue($template->refresh()->active);
    }

    public function test_a_template_test_through_an_unreachable_server_is_reported_as_a_failure(): void
    {
        $this->actingAs($this->editor());
        EmailConfiguration::factory()->asDefault()->create(['host' => '127.0.0.1', 'port' => 1, 'encryption' => 'none']);
        $template = EmailTemplate::factory()->create();

        $this->post(route('email-templates.send-test', $template), ['recipient' => 'to@example.test'])
            ->assertRedirect(route('email-templates.index'))
            ->assertSessionHas('error', static fn (string $error): bool => str_starts_with($error, __('The test email could not be sent: :reason', ['reason' => ''])));
    }

    private static function duplicateKey(): never
    {
        $exception = new PDOException('Duplicate name');
        $exception->errorInfo = ['23000', 19, 'UNIQUE constraint failed'];

        throw new QueryException('sqlite', 'write', [], $exception);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function templatePayload(array $overrides = []): array
    {
        return [
            'name' => 'Txantiloia',
            'subject_en' => ':subject:',
            'body_en' => '<p>:content:</p>',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function configurationPayload(array $overrides = []): array
    {
        return [
            'name' => 'Zerbitzaria',
            'sender_name' => 'Aplikazioa',
            'sender_email' => 'noreply@example.com',
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => 'user',
            'password' => '',
            'encryption' => 'tls',
            ...$overrides,
        ];
    }

    private function editor(): User
    {
        return User::factory()->withPermissions(['email.view', 'email.edit'])->create();
    }
}
