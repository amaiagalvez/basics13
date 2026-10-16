<?php

namespace Basics13\Tests\Unit\Actions\Fortify;

use LogicException;
use Basics13\Tests\TestCase;
use Basics13\Tests\Fixtures\AuditedRecord;
use Basics13\Actions\Fortify\CreateNewUser;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CreateNewUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_a_users_model_that_is_not_an_authentication_user(): void
    {
        config(['auth.providers.users.model' => AuditedRecord::class]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The configured users model must extend the Laravel authentication user model.');

        (new CreateNewUser)->create([
            'name' => 'New User',
            'email' => 'new-user@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
    }

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
}
