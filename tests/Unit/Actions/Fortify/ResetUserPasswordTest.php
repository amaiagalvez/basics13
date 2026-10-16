<?php

namespace Basics13\Tests\Unit\Actions\Fortify;

use LogicException;
use Basics13\Tests\TestCase;
use Illuminate\Contracts\Auth\Authenticatable;
use Basics13\Actions\Fortify\ResetUserPassword;

class ResetUserPasswordTest extends TestCase
{
    public function test_it_rejects_a_user_that_is_not_an_eloquent_model(): void
    {
        $user = $this->createStub(Authenticatable::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The configured users model must be an Eloquent model.');

        (new ResetUserPassword)->reset($user, [
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
    }
}
