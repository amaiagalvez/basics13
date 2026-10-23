<?php

namespace Basics13\Database\Factories;

use Basics13\Models\Setting;
use Basics13\Enums\SettingType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    protected $model = Setting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $variable = str_replace('-', '_', fake()->unique()->slug(2));

        return [
            'package' => 'basics13',
            'class' => Setting::class,
            'variable' => $variable,
            'type' => SettingType::String,
            'value' => fake()->word(),
            'name_eu' => $variable.' (eu)',
            'name_es' => $variable.' (es)',
            'name_fr' => $variable.' (fr)',
            'name_en' => $variable.' (en)',
            'description_eu' => fake()->sentence(),
            'description_es' => fake()->sentence(),
            'description_fr' => fake()->sentence(),
            'description_en' => fake()->sentence(),
        ];
    }

    /**
     * An integer variable within the accepted bounds.
     */
    public function integer(?int $value = null): static
    {
        return $this->state([
            'type' => SettingType::Integer,
            'value' => $value === null ? null : (string) $value,
        ]);
    }
}
