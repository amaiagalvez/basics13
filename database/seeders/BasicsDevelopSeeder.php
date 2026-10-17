<?php

namespace Basics13\Database\Seeders;

use Basics13\Models\User;
use Illuminate\Database\Seeder;

class BasicsDevelopSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(UserSeeder::class);
        User::factory()->count(5)->create();
    }
}
