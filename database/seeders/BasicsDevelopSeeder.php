<?php

namespace Basics13\Database\Seeders;

use Illuminate\Database\Seeder;

class BasicsDevelopSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(UserSeeder::class);
        $this->call(DevelopUserSeeder::class);
    }
}
