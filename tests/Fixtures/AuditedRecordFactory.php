<?php

namespace Basics13\Tests\Fixtures;

use Basics13\Database\Factories\HasStates;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditedRecord>
 */
class AuditedRecordFactory extends Factory
{
    use HasStates;

    protected $model = AuditedRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'notes' => fake()->sentence(),
        ];
    }
}
