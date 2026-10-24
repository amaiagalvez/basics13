<?php

namespace Basics13\Tests\Feature\Email;

use Basics13\Models\User;
use Basics13\Models\EmailTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Events\QueryExecuted;
use Basics13\Tests\Feature\Users\UserTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EmailTemplateCrudTest extends UserTestCase
{
    use RefreshDatabase;

    public function test_an_email_editor_creates_updates_and_trashes_templates(): void
    {
        $this->actingAs($this->editor());

        $this->post(route('email-templates.store'), $this->payload(['name' => 'Ongi etorri']))
            ->assertRedirect(route('email-templates.index'))
            ->assertSessionHas('status', __('basics13::messages.created'));
        $template = EmailTemplate::query()->sole();

        $this->get(route('email-templates.index'))->assertOk()->assertSee('Ongi etorri');

        $this->put(route('email-templates.update', $template), $this->payload(['name' => 'Agurra', 'body_eu' => '<p>Kaixo</p>']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Agurra', $template->refresh()->name);
        $this->assertSame('<p>Kaixo</p>', $template->body_eu);

        $this->delete(route('email-templates.destroy', $template))
            ->assertSessionHas('status', __('basics13::messages.moved_to_trash'));
        $this->assertSoftDeleted($template);
    }

    public function test_viewing_needs_email_view_and_writing_needs_email_edit(): void
    {
        $template = EmailTemplate::factory()->create();

        $this->actingAs(User::factory()->withPermissions(['users.view'])->create());
        $this->get(route('email-templates.index'))->assertForbidden();

        $this->actingAs(User::factory()->withPermissions(['email.view'])->create());
        $this->get(route('email-templates.index'))->assertOk();
        $this->post(route('email-templates.store'), $this->payload())->assertForbidden();
        $this->put(route('email-templates.update', $template), $this->payload())->assertForbidden();
        $this->delete(route('email-templates.destroy', $template))->assertForbidden();
        $this->post(route('email-templates.send-test', $template), ['recipient' => 'a@example.com'])->assertForbidden();
    }

    public function test_english_is_required_and_the_banner_is_a_url(): void
    {
        $this->actingAs($this->editor());

        $this->post(route('email-templates.store'), $this->payload([
            'subject_en' => '',
            'body_en' => '',
            'banner' => 'not a url',
        ]))->assertSessionHasErrors(['subject_en', 'body_en', 'banner']);

        $this->assertDatabaseCount('BAS_email_templates', 0);
    }

    public function test_update_converts_a_concurrent_duplicate_name_into_a_validation_error(): void
    {
        $this->actingAs($this->editor());
        $template = EmailTemplate::factory()->create(['name' => 'Jatorrizkoa']);
        $competitorCreated = false;

        DB::listen(static function (QueryExecuted $query) use (&$competitorCreated): void {
            if ($competitorCreated || ! str_contains(strtolower($query->sql), 'bas_email_templates') || ! in_array('Lehiakidea', $query->bindings, true)) {
                return;
            }

            $competitorCreated = true;
            EmailTemplate::factory()->create(['name' => 'Lehiakidea']);
        });

        $this->putJson(route('email-templates.update', $template), $this->payload(['name' => 'Lehiakidea']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
        $this->assertSame('Jatorrizkoa', $template->refresh()->name);
    }

    public function test_archive_restore_and_force_delete(): void
    {
        $this->actingAs($this->editor());
        $template = EmailTemplate::factory()->create();

        $this->patch(route('email-templates.archive', $template))->assertSessionHasNoErrors();
        $this->assertFalse($template->refresh()->active);
        $this->get(route('email-templates.archived.index'))->assertOk()->assertSee($template->name);

        $template->delete();
        $this->get(route('email-templates.trash.index'))->assertOk()->assertSee($template->name);
        $this->patch(route('email-templates.trash.restore', $template->id))->assertSessionHasNoErrors();
        $this->assertNotSoftDeleted($template);

        $template->delete();
        $this->delete(route('email-templates.trash.destroy', $template->id))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('BAS_email_templates', ['id' => $template->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Txantiloia',
            'subject_en' => ':subject:',
            'body_en' => '<p>:content:</p>',
            ...$overrides,
        ];
    }

    private function editor(): User
    {
        return User::factory()->withPermissions(['email.view', 'email.edit'])->create();
    }
}
