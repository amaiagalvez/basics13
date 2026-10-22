<?php

namespace Basics13\Tests\Feature\Users;

use Basics13\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserCrudTest extends UserTestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('users.index'))->assertRedirect(route('login'));
        $this->post(route('users.store'), $this->account())
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('users', 0);
    }

    public function test_unverified_users_cannot_create_accounts(): void
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->post(route('users.store'), $this->account())
            ->assertRedirect(route('verification.notice'));

        $this->assertDatabaseCount('users', 1);
    }

    public function test_the_list_shows_every_active_account(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Actor account']));
        $listedAccount = User::factory()->create(['name' => 'Listed account']);
        User::factory()->archived()->create(['name' => 'Archived account']);

        $this->get(route('users.index'))
            ->assertOk()
            ->assertSee('Actor account')
            ->assertSee('Listed account')
            ->assertSee('data-test="user-name-'.$listedAccount->id.'"', false)
            ->assertDontSee('Archived account');
    }

    public function test_the_lists_offer_advanced_filters(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Actor account', 'email' => 'actor@example.test']));
        User::factory()->create(['name' => 'Ane account', 'email' => 'ane@client.test']);
        User::factory()->archived()->create(['name' => 'Archived ane', 'email' => 'archived@client.test']);
        User::factory()->archived()->create(['name' => 'Archived other', 'email' => 'other@example.test']);
        $emailFilter = ['filters' => ['email' => ['operator' => 'ends_with', 'value' => 'client.test']]];

        $this->get(route('users.index', $emailFilter))
            ->assertOk()
            ->assertSee('data-test="user-advanced-search"', false)
            ->assertSeeText(__('basics13::messages.created_at'))
            ->assertSeeText(__('Status'))
            ->assertSee('data-test="user-filter-remove-email"', false)
            ->assertSee('Ane account')
            ->assertDontSee('Actor account');

        $this->get(route('users.archived.index', $emailFilter))
            ->assertOk()
            ->assertSee('Archived ane')
            ->assertDontSee('Archived other');
    }

    public function test_an_account_can_be_created_updated_and_trashed(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('users.store'), $this->account())
            ->assertRedirect(route('users.index'))
            ->assertSessionHasNoErrors();

        $account = User::query()->where('email', 'new@example.test')->firstOrFail();
        $this->assertSame('New account', $account->name);
        $this->assertTrue($account->active);

        $this->put(route('users.update', $account->getKey()), [
            'name' => 'Renamed account',
            'email' => 'new@example.test',
            'notes' => 'Updated notes',
        ])->assertRedirect(route('users.index'))->assertSessionHasNoErrors();

        $account->refresh();
        $this->assertSame('Renamed account', $account->name);
        $this->assertSame('Updated notes', $account->notes);

        $this->delete(route('users.destroy', $account->getKey()))
            ->assertRedirect(route('users.index'));

        $this->assertSoftDeleted($account);
    }

    public function test_a_delete_vetoed_by_a_listener_reports_that_the_account_cannot_be_deleted(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Zzz actor']));
        $account = User::factory()->create();

        // A `deleting` hook that returns false cancels the delete, the way a guard that finds a
        // related record does. The trashed account keeps its audit trail and blocks nothing else,
        // so the controller only reports the outcome.
        User::deleting(static fn (): bool => false);

        $this->delete(route('users.destroy', $account->getKey()))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('error', __('basics13::messages.cannot_delete_related'));

        $this->assertNotSoftDeleted($account);
    }

    public function test_the_password_is_hashed_and_never_stored_in_clear(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('users.store'), $this->account(password: 'secret-password'))
            ->assertSessionHasNoErrors();

        $account = User::query()->where('email', 'new@example.test')->firstOrFail();

        $this->assertNotSame('secret-password', $account->password);
        $this->assertTrue(Hash::check('secret-password', $account->password));
    }

    public function test_an_edit_without_a_password_keeps_the_current_one(): void
    {
        $this->actingAs(User::factory()->create());
        $account = User::factory()->create();
        $originalPassword = $account->password;

        $this->put(route('users.update', $account->getKey()), [
            'name' => 'Renamed account',
            'email' => $account->email,
        ])->assertRedirect(route('users.index'))->assertSessionHasNoErrors();

        $account->refresh();
        $this->assertSame('Renamed account', $account->name);
        $this->assertSame($originalPassword, $account->password);
    }

    public function test_an_edit_with_a_password_changes_it(): void
    {
        $account = User::factory()->create();
        $this->actingAs($account);

        $this->put(route('users.update', $account->getKey()), [
            'name' => $account->name,
            'email' => $account->email,
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertRedirect(route('users.index'))->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('brand-new-password', $account->refresh()->password));
    }

    public function test_an_edit_requires_the_password_confirmation(): void
    {
        $account = User::factory()->create();
        $this->actingAs($account);

        $this->from(route('users.index'))->put(route('users.update', $account->getKey()), [
            'name' => $account->name,
            'email' => $account->email,
            'password' => 'brand-new-password',
        ])->assertRedirect(route('users.index'))->assertSessionHasErrors('password');
    }

    public function test_another_account_password_cannot_be_changed(): void
    {
        $this->actingAs(User::factory()->create());
        $account = User::factory()->create();
        $originalPassword = $account->password;

        $this->from(route('users.index'))->put(route('users.update', $account->getKey()), [
            'name' => $account->name,
            'email' => $account->email,
            'password' => 'taken-over-password',
            'password_confirmation' => 'taken-over-password',
        ])->assertRedirect(route('users.index'))
            ->assertSessionHasErrors(['password' => __('basics13::messages.own_account_only')]);

        $this->assertSame($originalPassword, $account->refresh()->password);
    }

    public function test_another_account_email_cannot_be_changed(): void
    {
        $this->actingAs(User::factory()->create());
        $account = User::factory()->create(['email' => 'holder@example.test']);

        $this->from(route('users.index'))->put(route('users.update', $account->getKey()), [
            'name' => $account->name,
            'email' => 'attacker@example.test',
        ])->assertRedirect(route('users.index'))
            ->assertSessionHasErrors(['email' => __('basics13::messages.own_account_only')]);

        $this->assertSame('holder@example.test', $account->refresh()->email);
    }

    public function test_the_account_holder_can_change_its_own_email(): void
    {
        $account = User::factory()->create(['email' => 'old@example.test']);
        $this->actingAs($account);

        $this->put(route('users.update', $account->getKey()), [
            'name' => $account->name,
            'email' => 'new-own@example.test',
        ])->assertRedirect(route('users.index'))->assertSessionHasNoErrors();

        $this->assertSame('new-own@example.test', $account->refresh()->email);
    }

    public function test_the_edit_payload_tells_the_drawer_which_account_is_the_own_one(): void
    {
        $actor = User::factory()->create(['name' => 'Aaa actor']);
        $other = User::factory()->create(['name' => 'Bbb other']);

        $this->actingAs($actor)
            ->get(route('users.index'))
            ->assertOk()
            ->assertViewHas('list', function (array $list) use ($actor, $other): bool {
                $own = array_column(array_column($list['rows'], 'editPayload'), 'own', 'id');

                return $own[$actor->getKey()] === true && $own[$other->getKey()] === false;
            });
    }

    public function test_the_edit_form_is_bound_to_the_saved_notes(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Zzz actor']));
        $account = User::factory()->create(['name' => 'Aaa account', 'notes' => 'Account notes']);

        $this->get(route('users.index'))
            ->assertOk()
            ->assertSee('x-model="form.notes"', false)
            ->assertViewHas('list', function (array $list) use ($account): bool {
                $row = $list['rows'][0];

                // The drawer is filled from this payload, and the password must never be part of it.
                return $row['id'] === $account->getKey()
                    && $row['editPayload']['notes'] === 'Account notes'
                    && ! array_key_exists('password', $row['editPayload']);
            });
    }

    public function test_the_email_is_unique(): void
    {
        $this->actingAs(User::factory()->create());
        User::factory()->create(['email' => 'taken@example.test']);

        $this->from(route('users.index'))
            ->post(route('users.store'), $this->account(email: 'taken@example.test'))
            ->assertRedirect(route('users.index'))
            ->assertSessionHasErrors('email');

        $this->assertSame(1, User::query()->where('email', 'taken@example.test')->count());
    }

    public function test_the_email_stays_reserved_while_the_account_is_in_the_trash(): void
    {
        $this->actingAs(User::factory()->create());
        $account = User::factory()->create(['email' => 'reserved@example.test']);
        $account->delete();

        $this->postJson(route('users.store'), $this->account(email: 'reserved@example.test'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertSame(0, User::query()->where('email', 'reserved@example.test')->count());
        $this->assertSoftDeleted($account);
    }

    public function test_store_converts_a_concurrent_duplicate_email_into_a_validation_error(): void
    {
        $this->actingAs(User::factory()->create());
        $email = 'concurrent@example.test';
        $this->createDuplicateEmailAfterValidation($email);

        $this->postJson(route('users.store'), $this->account(email: $email))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertSame(1, User::query()->where('email', $email)->count());
    }

    public function test_update_converts_a_concurrent_duplicate_email_into_a_validation_error(): void
    {
        $this->actingAs(User::factory()->create());
        $account = User::factory()->create(['email' => 'original@example.test']);
        $email = 'concurrent@example.test';
        $this->createDuplicateEmailAfterValidation($email);

        $this->putJson(route('users.update', $account->getKey()), [
            'name' => $account->name,
            'email' => $email,
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertSame('original@example.test', $account->refresh()->email);
    }

    /**
     * @return array<string, string>
     */
    private function account(
        string $email = 'new@example.test',
        string $password = 'secret-password',
    ): array {
        return [
            'name' => 'New account',
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $password,
        ];
    }

    /**
     * Slips a competing account with the same email in between the unique rule and the write, which
     * is the race the database index has to absorb.
     */
    private function createDuplicateEmailAfterValidation(string $email): void
    {
        $table = (new User)->getTable();
        $competitorCreated = false;

        DB::listen(static function (QueryExecuted $query) use ($email, $table, &$competitorCreated): void {
            if (
                $competitorCreated
                || ! str_starts_with(strtolower(ltrim($query->sql)), 'select')
                || ! str_contains(strtolower($query->sql), strtolower($table))
                || ! in_array($email, $query->bindings, true)
            ) {
                return;
            }

            $competitorCreated = true;
            User::factory()->create(['email' => $email]);
        });
    }
}
