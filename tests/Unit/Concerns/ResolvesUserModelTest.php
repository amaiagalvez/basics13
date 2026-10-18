<?php

namespace Basics13\Tests\Unit\Concerns;

use stdClass;
use LogicException;
use Basics13\Models\User;
use Basics13\Tests\TestCase;
use Basics13\Concerns\ResolvesUserModel;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The trait is the single guard every class that reads `auth.providers.users.model` goes through,
 * so both the configured model and the rejection of a bad one are tested here.
 */
class ResolvesUserModelTest extends TestCase
{
    public function test_it_resolves_the_model_the_host_configures(): void
    {
        config(['auth.providers.users.model' => User::class]);

        $this->assertSame(User::class, $this->userModelClass(new UsesUserModel));
    }

    #[DataProvider('invalidModels')]
    public function test_it_rejects_a_configured_users_model_that_is_not_eloquent(mixed $configuredModel): void
    {
        config(['auth.providers.users.model' => $configuredModel]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The configured users model must be an Eloquent model.');

        $this->userModelClass(new UsesUserModel);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidModels(): array
    {
        return [
            'not even a string' => [null],
            'a class that is not a model' => [stdClass::class],
        ];
    }

    private function userModelClass(object $subject): string
    {
        $method = new \ReflectionMethod($subject, 'userModelClass');
        $method->setAccessible(true);

        /** @var string */
        return $method->invoke($subject);
    }
}

final class UsesUserModel
{
    use ResolvesUserModel;
}
