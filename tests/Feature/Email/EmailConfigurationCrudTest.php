<?php

namespace Basics13\Tests\Feature\Email;

use Basics13\Models\User;
use Illuminate\Support\Facades\DB;
use Basics13\Models\EmailConfiguration;
use Illuminate\Database\Events\QueryExecuted;
use Basics13\Tests\Feature\Users\UserTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EmailConfigurationCrudTest extends UserTestCase
{
    use RefreshDatabase;

    public function test_an_email_editor_creates_updates_and_trashes_configurations(): void
    {
        $this->actingAs($this->editor());

        $this->post(route('email-configurations.store'), $this->payload(['name' => 'Nagusia']))
            ->assertRedirect(route('email-configurations.index'))
            ->assertSessionHas('status', __('basics13::messages.created'));
        $configuration = EmailConfiguration::query()->sole();

        $this->get(route('email-configurations.index'))->assertOk()->assertSee('Nagusia');

        $this->put(route('email-configurations.update', $configuration), $this->payload(['name' => 'Nagusia bi', 'port' => 465]))
            ->assertSessionHasNoErrors();
        $this->assertSame('Nagusia bi', $configuration->refresh()->name);
        $this->assertSame(465, $configuration->port);

        $this->delete(route('email-configurations.destroy', $configuration))
            ->assertSessionHas('status', __('basics13::messages.moved_to_trash'));
        $this->assertSoftDeleted($configuration);
    }

    public function test_viewing_needs_email_view_and_writing_needs_email_edit(): void
    {
        $configuration = EmailConfiguration::factory()->create();

        $this->actingAs(User::factory()->withPermissions(['users.view'])->create());
        $this->get(route('email-configurations.index'))->assertForbidden();

        $this->actingAs(User::factory()->withPermissions(['email.view'])->create());
        $this->get(route('email-configurations.index'))->assertOk();
        $this->post(route('email-configurations.store'), $this->payload())->assertForbidden();
        $this->put(route('email-configurations.update', $configuration), $this->payload())->assertForbidden();
        $this->delete(route('email-configurations.destroy', $configuration))->assertForbidden();
        $this->patch(route('email-configurations.archive', $configuration))->assertForbidden();
        $this->post(route('email-configurations.send-test', $configuration), ['recipient' => 'a@example.com'])->assertForbidden();
        $this->assertDatabaseCount('BAS_email_configurations', 1);
    }

    public function test_the_fields_are_validated(): void
    {
        $this->actingAs($this->editor());

        $this->post(route('email-configurations.store'), $this->payload([
            'sender_email' => 'not-an-email',
            'port' => 'abc',
            'encryption' => 'starttls',
            'host' => '',
        ]))->assertSessionHasErrors(['sender_email', 'port', 'encryption', 'host']);

        $this->assertDatabaseCount('BAS_email_configurations', 0);
    }

    public function test_the_name_is_unique_among_the_configurations_outside_the_trash(): void
    {
        $this->actingAs($this->editor());
        EmailConfiguration::factory()->archived()->create(['name' => 'Zaharra']);

        $this->post(route('email-configurations.store'), $this->payload(['name' => 'Zaharra']))
            ->assertSessionHasErrors('name');
    }

    public function test_store_converts_a_concurrent_duplicate_name_into_a_validation_error(): void
    {
        $this->actingAs($this->editor());
        $this->createDuplicateNameAfterValidation('Lehiakidea');

        $this->postJson(route('email-configurations.store'), $this->payload(['name' => 'Lehiakidea']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseCount('BAS_email_configurations', 1);
    }

    public function test_a_name_taken_by_a_trashed_configuration_opens_the_conflict(): void
    {
        $this->actingAs($this->editor());
        $trashed = EmailConfiguration::factory()->trashed()->create(['name' => 'Zaharra']);

        $this->post(route('email-configurations.store'), $this->payload(['name' => 'Zaharra']))
            ->assertSessionHas('deleted_email_configuration_conflict', fn (array $payload): bool => $payload['id'] === $trashed->id);

        $this->post(route('email-configurations.store'), $this->payload(['name' => 'Zaharra', 'reuse_deleted_name' => true]))
            ->assertSessionHas('status', __('basics13::messages.created'));
    }

    public function test_archive_activate_restore_and_force_delete(): void
    {
        $this->actingAs($this->editor());
        $configuration = EmailConfiguration::factory()->create();

        $this->patch(route('email-configurations.archive', $configuration))->assertSessionHasNoErrors();
        $this->assertFalse($configuration->refresh()->active);
        $this->get(route('email-configurations.archived.index'))->assertOk()->assertSee($configuration->name);

        $this->patch(route('email-configurations.archived.activate', $configuration))->assertSessionHasNoErrors();
        $this->assertTrue($configuration->refresh()->active);

        $configuration->delete();
        $this->get(route('email-configurations.trash.index'))->assertOk()->assertSee($configuration->name);
        $this->patch(route('email-configurations.trash.restore', $configuration->id))->assertSessionHasNoErrors();
        $this->assertNotSoftDeleted($configuration);

        $configuration->delete();
        $this->delete(route('email-configurations.trash.destroy', $configuration->id))
            ->assertSessionHas('status', __('basics13::messages.permanently_deleted'));
        $this->assertDatabaseMissing('BAS_email_configurations', ['id' => $configuration->id]);
    }

    public function test_the_password_is_encrypted_and_never_reaches_the_page(): void
    {
        $this->actingAs($this->editor());

        $this->post(route('email-configurations.store'), $this->payload(['password' => 'super-secret-pw']));
        $configuration = EmailConfiguration::query()->sole();

        $stored = (string) DB::table('BAS_email_configurations')->value('password');
        $this->assertNotSame('super-secret-pw', $stored);
        $this->assertSame('super-secret-pw', $configuration->password);
        $this->assertArrayNotHasKey('password', $configuration->toArray());

        $this->get(route('email-configurations.index'))
            ->assertOk()
            ->assertDontSee('super-secret-pw')
            ->assertDontSee($stored);
    }

    public function test_an_update_with_an_empty_password_keeps_the_stored_one(): void
    {
        $this->actingAs($this->editor());
        $configuration = EmailConfiguration::factory()->create(['password' => 'old-secret']);

        $this->put(route('email-configurations.update', $configuration), $this->payload(['password' => '']))
            ->assertSessionHasNoErrors();
        $this->assertSame('old-secret', $configuration->refresh()->password);

        $this->put(route('email-configurations.update', $configuration), $this->payload(['password' => 'new-secret']))
            ->assertSessionHasNoErrors();
        $this->assertSame('new-secret', $configuration->refresh()->password);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
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

    private function createDuplicateNameAfterValidation(string $name): void
    {
        $competitorCreated = false;

        DB::listen(static function (QueryExecuted $query) use ($name, &$competitorCreated): void {
            if (
                $competitorCreated
                || ! str_starts_with(strtolower(ltrim($query->sql)), 'select')
                || ! str_contains(strtolower($query->sql), 'bas_email_configurations')
                || ! in_array($name, $query->bindings, true)
            ) {
                return;
            }

            $competitorCreated = true;
            EmailConfiguration::factory()->create(['name' => $name]);
        });
    }
}
