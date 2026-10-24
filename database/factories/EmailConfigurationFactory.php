<?php

namespace Basics13\Database\Factories;

use Basics13\Models\EmailConfiguration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailConfiguration>
 */
class EmailConfigurationFactory extends Factory
{
    use HasStates;

    protected $model = EmailConfiguration::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'sender_name' => fake()->company(),
            'sender_email' => fake()->unique()->safeEmail(),
            'host' => 'smtp.example.test',
            'port' => 587,
            'username' => fake()->userName(),
            'password' => 'secret',
            'encryption' => 'tls',
        ];
    }

    /**
     * The row the managed mailer sends through.
     */
    public function asDefault(): static
    {
        return $this->state(fn (array $attributes): array => ['default' => true]);
    }
}
