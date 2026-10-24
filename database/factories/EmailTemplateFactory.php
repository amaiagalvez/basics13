<?php

namespace Basics13\Database\Factories;

use Basics13\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailTemplate>
 */
class EmailTemplateFactory extends Factory
{
    use HasStates;

    protected $model = EmailTemplate::class;

    /**
     * Only English is filled in, the language every other one falls back to.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'subject_en' => fake()->sentence(4),
            'body_en' => '<p>'.fake()->sentence().'</p>',
        ];
    }

    /**
     * The template every managed email is wrapped with when none is chosen.
     */
    public function asDefault(): static
    {
        return $this->state(fn (array $attributes): array => ['default' => true]);
    }
}
