<?php

namespace Basics13\Tests\Unit\Http\Controllers;

use PDOException;
use Basics13\Tests\TestCase;
use Illuminate\Database\QueryException;
use Basics13\Http\Controllers\UserTypeController;

/**
 * The unique index the database names tells which language collided; when it names none of them,
 * the error lands on the euskera name, the one every type has.
 */
class UserTypeControllerTest extends TestCase
{
    public function test_a_duplicate_naming_a_language_is_reported_on_that_field(): void
    {
        $this->assertSame('name_es', $this->duplicatedNameField("Duplicate entry for key 'BAS_user_types_active_name_es_unique'"));
        $this->assertSame('name_fr', $this->duplicatedNameField('UNIQUE constraint failed: BAS_user_types.name_fr'));
    }

    public function test_a_duplicate_naming_no_language_is_reported_on_the_euskera_name(): void
    {
        $this->assertSame('name_eu', $this->duplicatedNameField('UNIQUE constraint failed: unknown'));
    }

    private function duplicatedNameField(string $message): string
    {
        $exception = new QueryException('testing', 'insert', [], new PDOException($message));
        $reflection = new \ReflectionMethod(UserTypeController::class, 'duplicatedNameField');

        return $reflection->invoke(new UserTypeController, $exception);
    }
}
