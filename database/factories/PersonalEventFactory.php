<?php

namespace Basics13\Database\Factories;

use Basics13\Models\User;
use Carbon\CarbonImmutable;
use Basics13\Models\PersonalEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonalEvent>
 */
class PersonalEventFactory extends Factory
{
    protected $model = PersonalEvent::class;

    /**
     * A one-day event on a day of the current month.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->words(3, true),
            'notes' => null,
            'start_date' => CarbonImmutable::today()->startOfMonth()->addDays(fake()->numberBetween(0, 27))->toDateString(),
            'end_date' => null,
        ];
    }

    /**
     * Several days inside one month.
     */
    public function spanning(int $days = 3): static
    {
        return $this->state(fn (array $attributes): array => [
            'start_date' => CarbonImmutable::parse($attributes['start_date'])->startOfMonth()->addDays(10)->toDateString(),
            'end_date' => CarbonImmutable::parse($attributes['start_date'])->startOfMonth()->addDays(10 + $days - 1)->toDateString(),
        ]);
    }

    /**
     * From the last days of a month into the first ones of the next.
     */
    public function acrossMonths(): static
    {
        return $this->state(fn (array $attributes): array => [
            'start_date' => CarbonImmutable::parse($attributes['start_date'])->endOfMonth()->subDay()->toDateString(),
            'end_date' => CarbonImmutable::parse($attributes['start_date'])->endOfMonth()->addDays(2)->toDateString(),
        ]);
    }

    /**
     * Pinned to the given days, the end one optional.
     */
    public function on(string $startDate, ?string $endDate = null): static
    {
        return $this->state(['start_date' => $startDate, 'end_date' => $endDate]);
    }
}
