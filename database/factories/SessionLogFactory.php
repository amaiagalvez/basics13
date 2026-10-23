<?php

namespace Basics13\Database\Factories;

use Basics13\Enums\DeviceType;
use Basics13\Models\SessionLog;
use Basics13\Enums\SessionEvent;
use Basics13\Enums\LoginFailureReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionLog>
 */
class SessionLogFactory extends Factory
{
    protected $model = SessionLog::class;

    /**
     * Define the model's default state: a failed attempt without a known account.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'event' => SessionEvent::Failed,
            'failure_reason' => LoginFailureReason::WrongPassword,
            'login' => fake()->userName(),
            'ip' => fake()->ipv4(),
            'user_agent' => $userAgent = fake()->userAgent(),
            'device_type' => DeviceType::fromUserAgent($userAgent),
            'lockout_minutes' => null,
            'session_key' => null,
        ];
    }

    /**
     * A successful login, tied to its session by the key the logout lookup uses.
     */
    public function login(): static
    {
        $userModel = config()->string('auth.providers.users.model');

        if (! is_subclass_of($userModel, Model::class)) {
            throw new \InvalidArgumentException('The users authentication provider must use an Eloquent model.');
        }

        return $this->state(fn (array $attributes): array => [
            'user_id' => static::getRecordOrNew($userModel)->getKey(),
            'event' => SessionEvent::Login,
            'failure_reason' => null,
            'session_key' => fake()->uuid(),
        ]);
    }

    /**
     * A lockout row: the attempt counter reached the maximum and the login is blocked.
     */
    public function lockout(int $minutes = 15): static
    {
        return $this->state(fn (array $attributes): array => [
            'event' => SessionEvent::Lockout,
            'failure_reason' => null,
            'lockout_minutes' => $minutes,
        ]);
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass
     * @return TModel
     */
    protected static function getRecordOrNew(string $modelClass): Model
    {
        /** @phpstan-ignore staticMethod.notFound (the configured users model has a factory) */
        return $modelClass::inRandomOrder()->first() ?? $modelClass::factory()->createOne();
    }
}
