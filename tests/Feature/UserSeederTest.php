<?php

namespace Basics13\Tests\Feature;

use Basics13\Models\User;
use Illuminate\Support\Facades\Hash;
use Basics13\Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserSeederTest extends FeatureTestCase
{
    use RefreshDatabase;

    public function test_it_creates_user_with_a_hashed_password(): void
    {
        $this->seed(UserSeeder::class);

        $user = User::where('email', 'info@amaia.eus')->firstOrFail();

        $this->assertSame('Amaia', $user->name);
        $this->assertTrue(Hash::check('123456', $user->password));
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertSame(['admin'], $user->roles()->pluck('slug')->all());
    }

    public function test_running_it_again_does_not_duplicate_the_user_or_reset_the_password(): void
    {
        $this->seed(UserSeeder::class);
        $user = User::where('email', 'info@amaia.eus')->firstOrFail();
        $user->update(['password' => 'changed-password']);

        $this->seed(UserSeeder::class);

        $this->assertSame(1, User::where('email', 'info@amaia.eus')->count());
        $this->assertTrue(Hash::check('changed-password', $user->refresh()->password));
        $this->assertSame(1, $user->roles()->count());
    }
}
