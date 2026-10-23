<?php

namespace Basics13\Tests\Feature\Sessions;

use Basics13\Models\User;
use Illuminate\Support\Carbon;
use Basics13\Models\SessionLog;
use Illuminate\Support\Facades\Route;
use Basics13\Tests\Feature\Roles\RoleTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The session list reuses the role tests' stand-in layout and host routes.
 */
class SessionLogListTest extends RoleTestCase
{
    use RefreshDatabase;

    public function test_the_combined_filter_keeps_the_failures_of_the_user_within_the_range_including_its_last_day(): void
    {
        $this->actingAs(User::factory()->withPermissions(['sessions.view'])->create());
        $ane = User::factory()->create(['name' => 'Ane']);
        $jon = User::factory()->create(['name' => 'Jon']);

        $inside = SessionLog::factory()->create(['user_id' => $ane->id, 'login' => 'inside-first', 'created_at' => Carbon::parse('2026-10-01 00:00:00')]);
        $lastDay = SessionLog::factory()->create(['user_id' => $ane->id, 'login' => 'inside-last-day', 'created_at' => Carbon::parse('2026-10-05 23:59:59')]);
        SessionLog::factory()->create(['user_id' => $ane->id, 'login' => 'before-range', 'created_at' => Carbon::parse('2026-09-30 23:59:59')]);
        SessionLog::factory()->create(['user_id' => $ane->id, 'login' => 'after-range', 'created_at' => Carbon::parse('2026-10-06 00:00:00')]);
        SessionLog::factory()->login()->create(['user_id' => $ane->id, 'login' => 'successful-login', 'created_at' => Carbon::parse('2026-10-03 10:00:00')]);
        SessionLog::factory()->create(['user_id' => $jon->id, 'login' => 'other-user', 'created_at' => Carbon::parse('2026-10-03 10:00:00')]);

        $this->get(route('sessions.index', ['filters' => [
            'user_id' => ['operator' => 'equals', 'value' => [(string) $ane->id]],
            'created_at' => ['operator' => 'between', 'value' => ['2026-10-01', '2026-10-05']],
            'event' => ['operator' => 'equals', 'value' => ['failed']],
        ]]))
            ->assertOk()
            ->assertViewHas('rows', static fn (array $rows): bool => array_column($rows, 'id') === [$lastDay->id, $inside->id]);
    }

    public function test_the_single_date_operators_use_whole_days(): void
    {
        $this->actingAs(User::factory()->withPermissions(['sessions.view'])->create());
        $before = SessionLog::factory()->create(['created_at' => Carbon::parse('2026-10-04 23:59:59')]);
        $startOfDay = SessionLog::factory()->create(['created_at' => Carbon::parse('2026-10-05 00:00:00')]);
        $endOfDay = SessionLog::factory()->create(['created_at' => Carbon::parse('2026-10-05 23:59:59')]);
        $after = SessionLog::factory()->create(['created_at' => Carbon::parse('2026-10-06 00:00:00')]);

        $ids = fn (string $operator, mixed $value): array => array_column(
            $this->get(route('sessions.index', ['filters' => ['created_at' => ['operator' => $operator, 'value' => $value]]]))->viewData('rows'),
            'id',
        );

        $this->assertSame([$endOfDay->id, $startOfDay->id], $ids('equals', '2026-10-05'));
        $this->assertSame([$before->id], $ids('before', '2026-10-05'));
        $this->assertSame([$after->id], $ids('after', '2026-10-05'));
        // A value that is not a date filters nothing instead of failing.
        $this->assertCount(4, $ids('equals', 'not-a-date'));
        $this->assertCount(4, $ids('between', ['2026-10-05', '']));
    }

    public function test_the_search_matches_the_typed_login_and_rows_come_newest_first(): void
    {
        $this->actingAs(User::factory()->withPermissions(['sessions.view'])->create());
        $older = SessionLog::factory()->create(['login' => 'ane@example.com', 'created_at' => now()->subHour()]);
        $sameMomentLow = SessionLog::factory()->create(['login' => 'ane@example.com', 'created_at' => now()]);
        $sameMomentHigh = SessionLog::factory()->create(['login' => 'ane@example.com', 'created_at' => now()]);
        SessionLog::factory()->create(['login' => 'jon@example.com']);

        $this->get(route('sessions.index', ['search' => 'ane@']))
            ->assertOk()
            ->assertViewHas('rows', static fn (array $rows): bool => array_column($rows, 'id') === [$sameMomentHigh->id, $sameMomentLow->id, $older->id]);
    }

    public function test_the_user_filter_offers_every_account_including_trashed_ones(): void
    {
        $this->actingAs(User::factory()->withPermissions(['sessions.view'])->create());
        $trashed = User::factory()->create(['name' => 'Gone account']);
        $trashed->delete();

        $this->get(route('sessions.index'))
            ->assertOk()
            ->assertViewHas('searchableFields', static fn (array $fields): bool => in_array('Gone account', ($fields['user_id']['options'])('active'), true));
    }

    public function test_a_trashed_account_is_still_named_on_its_rows(): void
    {
        $this->actingAs(User::factory()->withPermissions(['sessions.view'])->create());
        $trashed = User::factory()->create(['name' => 'Gone account']);
        SessionLog::factory()->create(['user_id' => $trashed->id]);
        $trashed->delete();

        $this->get(route('sessions.index'))->assertOk()->assertSee('Gone account');
    }

    public function test_the_user_agent_is_escaped(): void
    {
        $this->actingAs(User::factory()->withPermissions(['sessions.view'])->create());
        SessionLog::factory()->create(['user_agent' => '<script>alert(1)</script>']);

        $this->get(route('sessions.index'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_the_list_is_denied_without_permission(): void
    {
        $this->actingAs(User::factory()->withPermissions(['users.view'])->create());

        $this->get(route('sessions.index'))->assertForbidden();
    }

    public function test_the_register_has_no_write_route(): void
    {
        $sessionRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(static fn ($route): bool => str_starts_with((string) $route->getName(), 'sessions.'));

        $this->assertSame(['sessions.index'], $sessionRoutes->map->getName()->values()->all());
        $this->assertSame(['GET', 'HEAD'], $sessionRoutes->first()->methods());
    }
}
