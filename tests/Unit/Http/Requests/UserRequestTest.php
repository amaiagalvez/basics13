<?php

namespace Basics13\Tests\Unit\Http\Requests;

use Basics13\Tests\TestCase;
use Basics13\Http\Requests\UserRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Password;

class UserRequestTest extends TestCase
{
    public function test_prepare_for_validation_trims_name(): void
    {
        $request = new UserRequest;
        $request->merge(['name' => '  Trimmed  ']);

        $reflection = new \ReflectionMethod($request, 'prepareForValidation');
        $reflection->setAccessible(true);
        $reflection->invoke($request);

        $this->assertSame('Trimmed', $request->input('name'));
    }

    public function test_prepare_for_validation_does_nothing_when_name_not_string(): void
    {
        $request = new UserRequest;
        $request->merge(['name' => 123]);

        $reflection = new \ReflectionMethod($request, 'prepareForValidation');
        $reflection->setAccessible(true);
        $reflection->invoke($request);

        $this->assertSame(123, $request->input('name'));
    }

    public function test_rules_includes_profile_rules(): void
    {
        $request = new UserRequest;

        $reflection = new \ReflectionMethod($request, 'rules');
        $reflection->setAccessible(true);

        $rules = $reflection->invoke($request);

        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('lang', $rules);
    }

    public function test_rules_includes_password_rules(): void
    {
        $request = new UserRequest;

        $reflection = new \ReflectionMethod($request, 'rules');
        $reflection->setAccessible(true);

        $rules = $reflection->invoke($request);

        $this->assertArrayHasKey('password', $rules);
        // passwordRules() returns array of validation rules (strings), Password object is in the second position
        $this->assertIsArray($rules['password']);
        $this->assertNotEmpty($rules['password']);
    }

    public function test_role_ids_returns_null_when_assign_roles_is_false(): void
    {
        $request = new UserRequest;
        $request->merge(['assign_roles' => false, 'role_ids' => [1, 2]]);

        $reflection = new \ReflectionMethod($request, 'roleIds');
        $reflection->setAccessible(true);

        $this->assertNull($reflection->invoke($request));
    }

    public function test_messages_returns_custom_messages(): void
    {
        $request = new UserRequest;

        $messages = $request->messages();

        $this->assertArrayHasKey('password.prohibited', $messages);
        $this->assertArrayHasKey('email.in', $messages);
        $this->assertArrayHasKey('assign_roles.prohibited', $messages);
        $this->assertArrayHasKey('role_ids.prohibited', $messages);
    }

    public function test_held_role_ids_returns_empty_when_record_has_no_roles_method(): void
    {
        $request = new UserRequest;

        // Create a mock model that doesn't have a roles method
        $record = new class extends Model
        {
            protected $table = 'users';
        };

        $reflection = new \ReflectionMethod($request, 'heldRoleIds');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($request, $record);

        $this->assertSame([], $result);
    }

    public function test_held_types_returns_empty_when_record_has_no_user_types_method(): void
    {
        $record = new class extends Model
        {
            protected $table = 'users';
        };

        $reflection = new \ReflectionMethod(new UserRequest, 'heldTypes');

        $this->assertSame([], $reflection->invoke(new UserRequest, $record));
    }
}
