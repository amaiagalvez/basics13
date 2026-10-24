<?php

namespace Basics13\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Basics13\Concerns\ResolvesUserModel;
use Illuminate\Database\Eloquent\Builder;
use Basics13\Policies\PersonalEventPolicy;
use Basics13\Database\Factories\PersonalEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;

/**
 * An event a user writes on their own calendar. Plain CRUD: no trash, no `active` flag, and the
 * rows go with their owner. The owner is never mass assigned; it is always the signed-in user.
 *
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string|null $notes
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable|null $end_date
 * @property-read CarbonImmutable|null $created_at
 * @property-read CarbonImmutable|null $updated_at
 */
#[Fillable(['title', 'notes', 'start_date', 'end_date'])]
#[UsePolicy(PersonalEventPolicy::class)]
#[UseFactory(PersonalEventFactory::class)]
class PersonalEvent extends Model
{
    /** @use HasFactory<PersonalEventFactory> */
    use HasFactory, ResolvesUserModel;

    protected $table = 'BAS_personal_events';

    /**
     * @return array{start_date: 'immutable_date', end_date: 'immutable_date'}
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
        ];
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo($this->userModelClass(), 'user_id');
    }

    /**
     * The last day the event covers: its end date, or its only day when it has none.
     */
    public function lastDay(): CarbonImmutable
    {
        return $this->end_date ?? $this->start_date;
    }

    /**
     * The events of one user that touch the days between both bounds, both included. The upper
     * bound is compared as "before the next day" so it holds whether the engine keeps a plain date
     * or a date with a midnight time.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOwnedWithin(Builder $query, int|string $userId, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return $query
            ->where('user_id', $userId)
            ->where('start_date', '<', $to->addDay()->toDateString())
            ->where(static fn (Builder $query): Builder => $query
                ->where('end_date', '>=', $from->toDateString())
                ->orWhere(static fn (Builder $query): Builder => $query
                    ->whereNull('end_date')
                    ->where('start_date', '>=', $from->toDateString())));
    }
}
