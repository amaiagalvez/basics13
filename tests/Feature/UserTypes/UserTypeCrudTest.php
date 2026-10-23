<?php

namespace Basics13\Tests\Feature\UserTypes;

use Basics13\Models\User;
use Basics13\Models\UserType;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Database\Events\QueryExecuted;
use Basics13\Tests\Feature\Users\UserTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserTypeCrudTest extends UserTestCase
{
    use RefreshDatabase;

    public function test_a_type_manager_creates_updates_and_deletes_user_types(): void
    {
        $this->actingAs($this->manager());

        $this->post(route('user-types.store'), ['name_eu' => 'Irakasleak', 'name_es' => 'Profesorado'])
            ->assertRedirect(route('user-types.index'))
            ->assertSessionHas('status', __('basics13::messages.created'));
        $type = UserType::query()->sole();

        $this->get(route('user-types.index'))->assertOk()->assertSee('Irakasleak');

        $this->put(route('user-types.update', $type), ['name_eu' => 'Irakasle taldea'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Irakasle taldea', $type->refresh()->name_eu);

        $this->delete(route('user-types.destroy', $type))
            ->assertSessionHas('status', __('basics13::messages.moved_to_trash'));
        $this->assertSoftDeleted($type);
    }

    public function test_the_resource_needs_the_user_type_permissions(): void
    {
        $this->actingAs(User::factory()->withPermissions(['users.manage'])->create());

        $this->get(route('user-types.index'))->assertForbidden();
        $this->post(route('user-types.store'), ['name_eu' => 'Mota'])->assertForbidden();
        $this->assertDatabaseCount('BAS_user_types', 0);
    }

    public function test_a_type_held_by_an_active_or_a_trashed_account_cannot_be_deleted(): void
    {
        $this->actingAs($this->manager());
        $type = UserType::factory()->create();
        $account = User::factory()->create();
        $account->userTypes()->attach($type);

        $this->delete(route('user-types.destroy', $type))
            ->assertSessionHas('error', __('basics13::messages.cannot_delete_related'));
        $this->assertNotSoftDeleted($type);

        $account->delete();

        $this->delete(route('user-types.destroy', $type))
            ->assertSessionHas('error', __('basics13::messages.cannot_delete_related'));
        $this->assertNotSoftDeleted($type);
    }

    public function test_a_held_type_is_not_deleted_permanently_either(): void
    {
        $type = UserType::factory()->create();
        User::factory()->create()->userTypes()->attach($type);

        $this->assertFalse($type->forceDelete());
        $this->assertDatabaseHas('BAS_user_types', ['id' => $type->id]);

        // The foreign key refuses it even when the model hook is skipped.
        $this->expectException(QueryException::class);
        DB::table('BAS_user_types')->where('id', $type->id)->delete();
    }

    public function test_deleting_an_account_for_good_releases_its_types(): void
    {
        $this->actingAs($this->manager());
        $type = UserType::factory()->create();
        $account = User::factory()->create();
        $account->userTypes()->attach($type);

        $account->forceDelete();

        $this->assertDatabaseMissing('BAS_user_user_type', ['user_type_id' => $type->id]);
        $this->delete(route('user-types.destroy', $type))
            ->assertSessionHas('status', __('basics13::messages.moved_to_trash'));
    }

    public function test_each_language_name_is_unique_among_the_types_outside_the_trash(): void
    {
        $this->actingAs($this->manager());
        UserType::factory()->create(['name_eu' => 'Irakasleak']);
        UserType::factory()->archived()->create(['name_eu' => 'Zaharra']);

        $this->post(route('user-types.store'), ['name_eu' => 'Irakasleak'])->assertSessionHasErrors('name_eu');
        $this->post(route('user-types.store'), ['name_eu' => 'Zaharra'])->assertSessionHasErrors('name_eu');
    }

    public function test_store_converts_a_concurrent_duplicate_name_into_a_validation_error(): void
    {
        $this->actingAs($this->manager());
        $this->createDuplicateNameAfterValidation('Mota konkurrentea');

        $this->postJson(route('user-types.store'), ['name_eu' => 'Mota konkurrentea'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name_eu');

        $this->assertDatabaseCount('BAS_user_types', 1);
    }

    public function test_update_converts_a_concurrent_duplicate_name_into_a_validation_error(): void
    {
        $this->actingAs($this->manager());
        $type = UserType::factory()->create(['name_eu' => 'Mota originala']);
        $this->createDuplicateNameAfterValidation('Mota konkurrentea');

        $this->putJson(route('user-types.update', $type), ['name_eu' => 'Mota konkurrentea'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name_eu');

        $this->assertSame('Mota originala', $type->refresh()->name_eu);
    }

    public function test_archive_restore_and_force_delete(): void
    {
        $this->actingAs($this->manager());
        $type = UserType::factory()->create();

        $this->patch(route('user-types.archive', $type))->assertSessionHasNoErrors();
        $this->assertFalse($type->refresh()->active);
        $this->get(route('user-types.archived.index'))->assertOk()->assertSee($type->name_eu);

        $type->delete();
        $this->get(route('user-types.trash.index'))->assertOk()->assertSee($type->name_eu);
        $this->patch(route('user-types.trash.restore', $type->id))->assertSessionHasNoErrors();
        $this->assertNotSoftDeleted($type);

        $type->delete();
        $this->delete(route('user-types.trash.destroy', $type->id))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('BAS_user_types', ['id' => $type->id]);
    }

    private function manager(): User
    {
        return User::factory()->withPermissions(['user_types.manage'])->create();
    }

    /**
     * A write that lost the race to a concurrent one is refused by the database, and the request
     * turns that refusal into the validation error a form shows on the field.
     */
    private function createDuplicateNameAfterValidation(string $name): void
    {
        $competitorCreated = false;

        DB::listen(static function (QueryExecuted $query) use ($name, &$competitorCreated): void {
            if (
                $competitorCreated
                || ! str_starts_with(strtolower(ltrim($query->sql)), 'select')
                || ! str_contains(strtolower($query->sql), 'bas_user_types')
                || ! in_array($name, $query->bindings, true)
            ) {
                return;
            }

            $competitorCreated = true;
            UserType::factory()->create(['name_eu' => $name]);
        });
    }
}
