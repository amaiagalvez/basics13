<?php

namespace Basics13\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditActor>
 */
class AuditActorFactory extends Factory
{
    protected $model = AuditActor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
        ];
    }
}
