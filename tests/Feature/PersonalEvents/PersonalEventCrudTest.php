<?php

namespace Basics13\Tests\Feature\PersonalEvents;

use Basics13\Models\User;
use Carbon\CarbonImmutable;
use Basics13\Models\PersonalEvent;
use Basics13\Tests\Feature\Users\UserTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PersonalEventCrudTest extends UserTestCase
{
    use RefreshDatabase;

    public function test_any_user_creates_updates_and_deletes_their_own_events(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->from('/calendar');

        $this->post(route('personal-events.store'), ['title' => 'Dentista', 'start_date' => '2026-06-12'])
            ->assertRedirect('/calendar')
            ->assertSessionHas('status', __('basics13::messages.created'));
        $event = PersonalEvent::query()->sole();
        $this->assertSame($user->id, $event->user_id);
        $this->assertNull($event->end_date);

        $this->put(route('personal-events.update', $event), [
            'title' => 'Dentista',
            'start_date' => '2026-06-12',
            'end_date' => '2026-06-14',
            'notes' => 'Revisión',
        ])->assertSessionHasNoErrors()->assertRedirect('/calendar');
        $this->assertSame('2026-06-14', $event->refresh()->end_date?->toDateString());

        $this->delete(route('personal-events.destroy', $event))
            ->assertSessionHas('status', __('basics13::messages.deleted'));
        $this->assertModelMissing($event);
    }

    public function test_the_owner_is_the_signed_in_user_never_the_payload(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->post(route('personal-events.store'), [
            'title' => 'Reunión',
            'start_date' => '2026-06-12',
            'user_id' => $other->id,
        ]);

        $this->assertSame($user->id, PersonalEvent::query()->sole()->user_id);
    }

    public function test_an_event_of_another_user_cannot_be_touched(): void
    {
        $event = PersonalEvent::factory()->on('2026-06-12')->create(['title' => 'Ajeno']);
        $this->actingAs(User::factory()->create());

        $this->put(route('personal-events.update', $event), ['title' => 'Mío', 'start_date' => '2026-06-12'])
            ->assertForbidden();
        $this->delete(route('personal-events.destroy', $event))->assertForbidden();

        $this->assertSame('Ajeno', $event->refresh()->title);
    }

    public function test_invalid_dates_are_reported_on_their_field_and_nothing_is_saved(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('personal-events.store'), ['title' => '', 'start_date' => '12/06/2026'])
            ->assertSessionHasErrors(['title', 'start_date']);
        $this->post(route('personal-events.store'), ['title' => 'Viaje', 'start_date' => '2026-06-12', 'end_date' => '2026-06-11'])
            ->assertSessionHasErrors(['end_date'])
            ->assertSessionDoesntHaveErrors(['start_date']);
        $this->post(route('personal-events.store'), ['title' => 'Viaje', 'end_date' => '2026-06-11'])
            ->assertSessionHasErrors(['start_date']);

        $event = PersonalEvent::factory()->on('2026-06-12')->create();
        $this->actingAs(User::query()->findOrFail($event->user_id))->put(route('personal-events.update', $event), [
            'title' => 'Viaje',
            'start_date' => '2026-06-12',
            'end_date' => 'mañana',
        ])->assertSessionHasErrors(['end_date']);

        $this->assertDatabaseCount('BAS_personal_events', 1);
    }

    public function test_deleting_the_owner_takes_their_events_along(): void
    {
        $event = PersonalEvent::factory()->create();

        $event->user?->forceDelete();

        $this->assertModelMissing($event);
    }

    public function test_the_factory_builds_one_day_ranged_and_cross_month_events(): void
    {
        $oneDay = PersonalEvent::factory()->create();
        $ranged = PersonalEvent::factory()->spanning(4)->create();
        $crossing = PersonalEvent::factory()->acrossMonths()->create();

        $this->assertNull($oneDay->end_date);
        $this->assertSame(3, (int) $ranged->start_date->diffInDays($ranged->lastDay()));
        $this->assertNotSame($crossing->start_date->month, $crossing->lastDay()->month);
    }

    public function test_events_are_read_per_owner_and_by_the_days_they_touch(): void
    {
        $user = User::factory()->create();
        $make = fn (string $start, ?string $end = null): PersonalEvent => PersonalEvent::factory()
            ->for($user)->on($start, $end)->create();

        $inside = $make('2026-06-10');
        $before = $make('2026-06-01', '2026-06-07');
        $crossingIn = $make('2026-06-05', '2026-06-08');
        $crossingOut = $make('2026-06-14', '2026-06-20');
        $after = $make('2026-06-15');
        PersonalEvent::factory()->on('2026-06-10')->create();

        $found = PersonalEvent::query()
            ->ownedWithin($user->id, CarbonImmutable::parse('2026-06-08'), CarbonImmutable::parse('2026-06-14'))
            ->pluck('id')
            ->sort()
            ->values()
            ->all();

        $this->assertSame([$inside->id, $crossingIn->id, $crossingOut->id], $found);
        $this->assertNotContains($before->id, $found);
        $this->assertNotContains($after->id, $found);
    }
}
