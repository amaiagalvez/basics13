<?php

namespace Basics13\Tests\Feature\Users;

use Basics13\Models\Role;
use Basics13\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserImpersonationTest extends UserTestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_impersonate_normal_user(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        $this->actingAs($superAdmin)
            ->post(route('users.impersonate', $target->id))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status', __('basics13::messages.impersonation_started', ['name' => $target->name]));

        $this->assertEquals($target->id, auth()->id());
        $this->assertEquals($superAdmin->id, session('impersonator_id'));
    }

    public function test_superadmin_cannot_impersonate_self(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->post(route('users.impersonate', $superAdmin->id))
            ->assertForbidden();
    }

    public function test_superadmin_cannot_impersonate_another_superadmin(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $otherSuperAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->post(route('users.impersonate', $otherSuperAdmin->id))
            ->assertForbidden();
    }

    public function test_non_superadmin_cannot_impersonate(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('users.impersonate', $target->id))
            ->assertForbidden();
    }

    public function test_impersonated_user_can_leave_impersonation(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        $this->actingAs($superAdmin)
            ->post(route('users.impersonate', $target->id));

        $this->assertEquals($target->id, auth()->id());
        $this->assertEquals($superAdmin->id, session('impersonator_id'));

        $this->post(route('impersonation.leave'))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('status', __('basics13::messages.impersonation_ended'));

        $this->assertEquals($superAdmin->id, auth()->id());
        $this->assertNull(session('impersonator_id'));
    }

    public function test_leave_impersonation_without_active_impersonation_redirects(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->post(route('impersonation.leave'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', __('basics13::messages.no_active_impersonation'));
    }

    public function test_impersonated_user_creates_record_with_correct_audit(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();
        // Give target user permission to create users
        $adminRole = Role::where('slug', 'admin')->firstOrFail();
        $target->assignRole($adminRole);

        $this->actingAs($superAdmin)
            ->post(route('users.impersonate', $target->id));

        $this->assertEquals($target->id, auth()->id());

        // Create a new user while impersonating
        $newUser = User::factory()->make();
        $this->post(route('users.store'), [
            'name' => $newUser->name,
            'email' => $newUser->email,
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertRedirect(route('users.index'));

        $created = User::where('email', $newUser->email)->first();
        $this->assertNotNull($created);
        $this->assertEquals($target->id, $created->created_by); // created_by = impersonated user
        $this->assertEquals($superAdmin->id, $created->updated_by); // updated_by = original superadmin
    }

    public function test_impersonated_user_updates_record_with_correct_audit(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();
        $existingUser = User::factory()->create();
        // Give target user permission to edit users
        $adminRole = Role::where('slug', 'admin')->firstOrFail();
        $target->assignRole($adminRole);

        $this->actingAs($superAdmin)
            ->post(route('users.impersonate', $target->id));

        $this->assertEquals($target->id, auth()->id());

        // Update an existing user while impersonating
        $this->put(route('users.update', $existingUser->id), [
            'name' => 'Updated Name',
            'email' => $existingUser->email,
        ])->assertRedirect(route('users.index'));

        $existingUser->refresh();
        $this->assertEquals('Updated Name', $existingUser->name);
        $this->assertEquals($superAdmin->id, $existingUser->updated_by); // updated_by = original superadmin
    }
}
