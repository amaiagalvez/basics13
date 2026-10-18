<?php

namespace Basics13\Database\Seeders;

use Basics13\Models\User;
use Illuminate\Database\Seeder;

class DevelopUserSeeder extends Seeder
{
    private const STATE_WEIGHTS = ['active' => 12, 'archived' => 5, 'trashed' => 3];

    public function run(int $users = 100): void
    {
        $states = [];

        foreach (self::STATE_WEIGHTS as $state => $weight) {
            array_push($states, ...array_fill(0, $weight, $state));
        }

        for ($number = 1; $number <= $users; $number++) {
            $state = $states[($number - 1) % count($states)];
            $factory = match ($state) {
                'archived' => User::factory()->archived(),
                'trashed' => User::factory()->trashed(),
                default => User::factory(),
            };

            $factory->createOne([
                'name' => sprintf('Perf User %05d', $number),
                'email' => sprintf('perf.user.%05d@basics.test', $number),
            ]);
        }
    }
}
