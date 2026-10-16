<?php

namespace Basics13\Database\Factories;

use Illuminate\Database\Eloquent\Model;

/**
 * Provides common factory states for models with `active` and soft delete support.
 */
trait HasStates
{
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    public function trashed(): static
    {
        return $this->afterCreating(static function (Model $model): void {
            $model->delete();
        });
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass
     * @return TModel
     */
    public static function getRecordOrNew(string $modelClass): Model
    {
        return $modelClass::inRandomOrder()->first() ?? $modelClass::factory()->createOne();
    }

    /**
     * @return array{start_date: string|null, end_date: string|null}
     */
    public static function getDatesInRange(int $startYearsBack = -1, int $endYearsForward = 1, bool $allowNull = false): array
    {
        if ($allowNull && fake()->boolean(20)) {
            return ['start_date' => null, 'end_date' => null];
        }

        $startDate = fake()->dateTimeBetween("{$startYearsBack} year", 'now')->format('Y-m-d');
        $endDate = fake()->dateTimeBetween($startDate, "+{$endYearsForward} year")->format('Y-m-d');

        return ['start_date' => $startDate, 'end_date' => $endDate];
    }
}
