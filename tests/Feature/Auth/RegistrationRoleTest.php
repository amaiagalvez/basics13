<?php

namespace Basics13\Tests\Feature\Auth;

use LogicException;
use Basics13\Models\Role;
use Basics13\Models\User;
use Laravel\Fortify\Features;
use Illuminate\Testing\TestResponse;
use Basics13\Actions\Fortify\CreateNewUser;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RegistrationRoleTest extends AuthFeatureTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_without_a_configured_role_the_first_account_registered_has_no_roles_and_is_not_super_admin(): void
    {
        $this->register()->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'new@example.test')->firstOrFail();
        $this->assertFalse($user->is_super_admin);
        $this->assertSame(0, $user->roles()->count());
        $this->assertFalse($user->hasPermissionTo('users.view'));
    }

    public function test_the_configured_role_is_assigned_on_registration(): void
    {
        config(['basics13.roles.registration_role' => 'viewer']);

        $this->register()->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'new@example.test')->firstOrFail();
        $this->assertSame(['viewer'], $user->roles()->pluck('slug')->all());
        $this->assertFalse($user->is_super_admin);
    }

    public function test_a_system_role_is_never_given_on_registration(): void
    {
        config(['basics13.roles.registration_role' => 'admin']);

        $this->expectException(LogicException::class);

        (new CreateNewUser)->create([
            'name' => 'New account',
            'email' => 'new@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
    }

    public function test_a_registration_role_in_the_trash_is_a_misconfiguration(): void
    {
        config(['basics13.roles.registration_role' => 'viewer']);
        Role::query()->where('slug', 'viewer')->firstOrFail()->delete();

        $this->expectException(LogicException::class);

        (new CreateNewUser)->create([
            'name' => 'New account',
            'email' => 'new@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
    }

    /**
     * @return TestResponse<Response>
     */
    private function register(): TestResponse
    {
        return $this->post(route('register.store'), [
            'name' => 'New account',
            'email' => 'new@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
    }
}
