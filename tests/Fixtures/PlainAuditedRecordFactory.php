<?php

namespace Basics13\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlainAuditedRecord>
 */
class PlainAuditedRecordFactory extends Factory
{
    protected $model = PlainAuditedRecord::class;

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
