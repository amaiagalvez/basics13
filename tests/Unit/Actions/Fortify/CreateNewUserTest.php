<?php

namespace Basics13\Tests\Unit\Actions\Fortify;

use LogicException;
use Basics13\Models\User;
use Basics13\Tests\TestCase;
use Basics13\Actions\Fortify\CreateNewUser;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;

class CreateNewUserTest extends TestCase
{
    public function test_it_rejects_a_users_model_that_is_not_a_class_name(): void
    {
        config(['auth.providers.users.model' => null]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The configured users model must be a class name.');

        (new CreateNewUser)->create([
            'name' => 'New User',
            'email' => 'new-user@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
    }

    public function test_assign_registration_role_returns_early_when_slug_is_null(): void
    {
        $action = new CreateNewUser;

        $reflection = new \ReflectionMethod($action, 'assignRegistrationRole');
        $reflection->setAccessible(true);

        // Should not throw, just return early
        $this->expectNotToPerformAssertions();
        $reflection->invoke($action, new User);
    }

    public function test_assign_registration_role_throws_when_user_has_no_assign_role(): void
    {
        config(['basics13.roles.registration_role' => 'viewer']);

        $action = new CreateNewUser;

        $reflection = new \ReflectionMethod($action, 'assignRegistrationRole');
        $reflection->setAccessible(true);

        $user = new UserWithoutHasRoles;

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The configured users model must use the Basics13\\Concerns\\HasRoles concern.');

        $reflection->invoke($action, $user);
    }
}

final class UserWithoutHasRoles extends AuthenticatableUser {}
