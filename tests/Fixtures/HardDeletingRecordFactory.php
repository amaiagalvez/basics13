<?php

namespace Basics13\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HardDeletingRecord>
 */
class HardDeletingRecordFactory extends Factory
{
    protected $model = HardDeletingRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
        ];
    }
}
