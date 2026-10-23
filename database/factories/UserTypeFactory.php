<?php

namespace Basics13\Database\Factories;

use Basics13\Models\UserType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserType>
 */
class UserTypeFactory extends Factory
{
    use HasStates;

    protected $model = UserType::class;

    /**
     * Define the model's default state.
     *
     * Every language is filled with a value no other record of this factory has used, so a test can
     * count on the four unique active indexes not getting in each other's way.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name_eu' => fake()->unique()->words(2, true),
            'name_es' => fake()->unique()->words(2, true),
            'name_fr' => fake()->unique()->words(2, true),
            'name_en' => fake()->unique()->words(2, true),
        ];
    }
}
