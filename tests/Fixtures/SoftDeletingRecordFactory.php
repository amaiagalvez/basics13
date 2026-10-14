<?php

namespace Basics13\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SoftDeletingRecord>
 */
class SoftDeletingRecordFactory extends Factory
{
    protected $model = SoftDeletingRecord::class;

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
