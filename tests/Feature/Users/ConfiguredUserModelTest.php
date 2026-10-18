<?php

namespace Basics13\Tests\Feature\Users;

use Basics13\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The host may replace the account model. The resource reads it from `auth.providers.users.model`
 * when the routes are bound and when the policy is registered, and this is the configuration that
 * makes both of them point somewhere else than the package default.
 */
final class ConfiguredUserModelTest extends UserTestCase
{
    use RefreshDatabase;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('auth.providers.users.model', ConfiguredAccount::class);
    }

    public function test_the_resource_operates_on_the_model_the_host_configures(): void
    {
        $this->actingAs($this->configuredActor());

        $this->post(route('users.store'), [
            'name' => 'Configured account',
            'email' => 'configured@example.test',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertRedirect(route('users.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'configured@example.test']);

        $account = ConfiguredAccount::query()->where('email', 'configured@example.test')->firstOrFail();

        // The route binding resolved the configured model: a `Basics13\Models\User` binding would
        // have nothing to do with this row.
        $this->put(route('users.update', $account->getKey()), [
            'name' => 'Renamed configured account',
            'email' => 'configured@example.test',
        ])->assertRedirect(route('users.index'))->assertSessionHasNoErrors();

        $this->assertSame('Renamed configured account', $account->refresh()->name);

        $this->delete(route('users.destroy', $account->getKey()))
            ->assertRedirect(route('users.index'));

        $this->assertSoftDeleted($account);
    }

    /**
     * The configured model is not the package one, so the actor is made the same way a host would:
     * created through the model and verified explicitly, since only the real account columns are
     * fillable and the verification timestamp is not one of them.
     */
    private function configuredActor(): ConfiguredAccount
    {
        $actor = ConfiguredAccount::query()->create([
            'name' => 'Configured actor',
            'email' => 'actor@example.test',
            'password' => 'password',
        ]);

        $actor->markEmailAsVerified();

        return $actor;
    }
}

final class ConfiguredAccount extends User
{
    protected $table = 'users';
}
