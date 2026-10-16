<?php

namespace Basics13\Database\Seeders;

use LogicException;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userModel = config('auth.providers.users.model');

        if (! is_string($userModel) ||
            ! is_subclass_of($userModel, Model::class) ||
            ! is_subclass_of($userModel, Authenticatable::class)) {
            throw new LogicException('The configured users model must be an Eloquent authenticatable model.');
        }

        $userModel::query()->firstOrCreate(
            ['email' => 'info@amaia.eus'],
            [
                'name' => 'Amaia',
                'password' => '123456',
                'email_verified_at' => now(),
            ],
        );
    }
}
