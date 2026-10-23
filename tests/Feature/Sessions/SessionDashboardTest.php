<?php

namespace Basics13\Tests\Feature\Sessions;

use Basics13\Models\User;
use Illuminate\Support\Carbon;
use Basics13\Models\SessionLog;
use Basics13\Enums\LoginFailureReason;
use Basics13\Tests\Feature\Roles\RoleTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The administration dashboard reuses the role tests' stand-in layout and host routes.
 */
class SessionDashboardTest extends RoleTestCase
{
    use RefreshDatabase;

    public function test_it_counts_the_accounts_the_failures_and_the_daily_sign_ins_of_the_period(): void
    {
        $this->travelTo(Carbon::parse('2026-10-23 12:00:00'));
        $viewer = User::factory()->withPermissions(['sessions.view'])->create();
        $ane = User::factory()->create();
        $jon = User::factory()->create();
        User::factory()->create()->forceFill(['active' => false])->save();
        User::factory()->create()->delete();

        SessionLog::factory()->login()->count(2)->create(['user_id' => $ane->id, 'created_at' => now()]);
        SessionLog::factory()->login()->create(['user_id' => $jon->id, 'created_at' => now()]);
        SessionLog::factory()->login()->create(['user_id' => $ane->id, 'created_at' => Carbon::parse('2026-10-17 00:00:00')]);
        SessionLog::factory()->login()->create(['user_id' => $ane->id, 'created_at' => Carbon::parse('2026-10-16 23:59:59')]);
        SessionLog::factory()->count(2)->create(['created_at' => now()->subDays(2)]);
        SessionLog::factory()->create(['failure_reason' => LoginFailureReason::Locked, 'created_at' => now()->subDay()]);
        SessionLog::factory()->lockout()->create(['created_at' => now()->subDay()]);
        SessionLog::factory()->create(['created_at' => now()->subDays(8)]);

        $this->actingAs($viewer)
            ->get(route('sessions.dashboard', ['period' => 7]))
            ->assertOk()
            ->assertViewHas('period', 7)
            ->assertViewHas('summary', static fn (array $summary): bool => $summary['users'] === 4
                && $summary['failed'] === ['current' => 3, 'previous' => 1]
                && $summary['days'] === ['2026-10-17', '2026-10-18', '2026-10-19', '2026-10-20', '2026-10-21', '2026-10-22', '2026-10-23']
                && $summary['signIns'] === [1, 0, 0, 0, 0, 0, 3]
                && $summary['uniqueUsers'] === [1, 0, 0, 0, 0, 0, 2])
            ->assertSeeInOrder(['data-test="admin-dashboard-users"', '4', 'data-test="admin-dashboard-failed"', '3'], false);
    }

    public function test_it_sums_up_the_accounts_of_the_period(): void
    {
        $this->travelTo(Carbon::parse('2026-10-23 12:00:00'));
        $old = User::factory()->count(4)->create(['created_at' => now()->subMonth()]);
        $old[1]->forceFill(['active' => false, 'updated_at' => now()->subMonth()])->save();
        $old[2]->delete();
        User::factory()->unverified()->create();
        SessionLog::factory()->login()->create(['user_id' => $old[0]->id, 'created_at' => now()->subDay()]);
        // The viewer is an old, verified account that signed in during the period too.
        $viewer = User::factory()->withPermissions(['sessions.view'])->create(['created_at' => now()->subMonth()]);
        SessionLog::factory()->login()->create(['user_id' => $viewer->id, 'created_at' => now()->subDay()]);

        $this->actingAs($viewer)
            ->get(route('sessions.dashboard'))
            ->assertOk()
            ->assertViewHas('summary', static fn (array $summary): bool => $summary['users'] === 5
                && $summary['active'] === ['current' => 2, 'previous' => 0]
                && $summary['new'] === ['current' => 1, 'previous' => 0]
                && $summary['inactive'] === 2
                && $summary['pending'] === 1
                && $summary['growth'] === 25.0)
            ->assertSeeInOrder(['data-test="admin-dashboard-growth-value"', '+25%'], false)
            ->assertDontSee('data-test="admin-dashboard-new-change"', false);
    }

    public function test_the_growth_has_no_value_without_earlier_accounts(): void
    {
        $this->travelTo(Carbon::parse('2026-10-23 12:00:00'));

        $this->actingAs(User::factory()->withPermissions(['sessions.view'])->create())
            ->get(route('sessions.dashboard'))
            ->assertOk()
            ->assertViewHas('summary', static fn (array $summary): bool => $summary['growth'] === null)
            ->assertSeeInOrder(['data-test="admin-dashboard-growth-value"', '—'], false);
    }

    public function test_the_flow_figures_are_compared_with_the_previous_period(): void
    {
        $this->travelTo(Carbon::parse('2026-10-23 12:00:00'));
        SessionLog::factory()->count(4)->create(['created_at' => now()->subDays(10)]);
        SessionLog::factory()->count(5)->create(['created_at' => now()->subDay()]);

        $this->actingAs(User::factory()->withPermissions(['sessions.view'])->create())
            ->get(route('sessions.dashboard'))
            ->assertOk()
            ->assertViewHas('summary', static fn (array $summary): bool => $summary['failed'] === ['current' => 5, 'previous' => 4])
            ->assertSeeInOrder(['data-test="admin-dashboard-failed-change"', 'text-red-700', '+25% vs the previous 7 days'], false);
    }

    public function test_only_the_alerts_whose_condition_holds_are_shown(): void
    {
        $this->travelTo(Carbon::parse('2026-10-23 12:00:00'));
        $viewer = User::factory()->withPermissions(['sessions.view'])->create(['created_at' => now()->subMonth()]);
        User::factory()->count(2)->create(['created_at' => now()->subDays(10)]);
        User::factory()->count(5)->create(['created_at' => now()->subDays(2)]);
        User::factory()->unverified()->create(['created_at' => now()->subDays(2)]);
        User::all()->each(static fn (User $user) => SessionLog::factory()->login()->create(['user_id' => $user->id, 'created_at' => now()->subDay()]));

        $this->actingAs($viewer)
            ->get(route('sessions.dashboard'))
            ->assertOk()
            ->assertViewHas('summary', static fn (array $summary): bool => $summary['alerts'] === ['pending' => 1, 'inactive' => 0, 'surge' => true, 'suspended' => 0])
            ->assertSee('data-test="admin-alert-pending"', false)
            ->assertSee('data-test="admin-alert-surge"', false)
            ->assertDontSee('data-test="admin-alert-inactive"', false)
            ->assertDontSee('data-test="admin-alert-suspended"', false);
    }

    public function test_inactivity_and_recent_archiving_raise_their_alerts(): void
    {
        $this->travelTo(Carbon::parse('2026-10-23 12:00:00'));
        $viewer = User::factory()->withPermissions(['sessions.view'])->create();
        SessionLog::factory()->login()->create(['user_id' => $viewer->id, 'created_at' => now()]);
        $idle = User::factory()->create();
        SessionLog::factory()->login()->create(['user_id' => $idle->id, 'created_at' => now()->subDays(31)]);
        User::factory()->create()->forceFill(['active' => false])->save();

        $this->actingAs($viewer)
            ->get(route('sessions.dashboard'))
            ->assertOk()
            ->assertViewHas('summary', static fn (array $summary): bool => $summary['alerts']['inactive'] === 1
                && $summary['alerts']['suspended'] === 1
                && $summary['alerts']['surge'] === false);
    }

    public function test_there_is_no_alert_block_without_alerts(): void
    {
        $viewer = User::factory()->withPermissions(['sessions.view'])->create();
        SessionLog::factory()->login()->create(['user_id' => $viewer->id, 'created_at' => now()]);

        $this->actingAs($viewer)
            ->get(route('sessions.dashboard'))
            ->assertOk()
            ->assertDontSee('data-test="admin-dashboard-alerts"', false);
    }

    public function test_without_a_known_period_it_shows_seven_days_with_that_button_active(): void
    {
        $this->actingAs(User::factory()->withPermissions(['sessions.view'])->create());

        foreach ([[], ['period' => 45]] as $query) {
            $this->get(route('sessions.dashboard', $query))
                ->assertOk()
                ->assertViewHas('period', 7)
                ->assertViewHas('summary', static fn (array $summary): bool => count($summary['days']) === 7)
                ->assertSee('aria-current="page" data-test="admin-dashboard-period-7"', false);
        }
    }

    public function test_the_failed_figure_opens_the_list_narrowed_to_the_same_result_and_days(): void
    {
        $this->travelTo(Carbon::parse('2026-10-23 12:00:00'));

        $expected = route('sessions.index', ['filters' => [
            'event' => ['operator' => 'equals', 'value' => ['failed']],
            'created_at' => ['operator' => 'between', 'value' => ['2026-10-17', '2026-10-23']],
        ]]);

        $this->actingAs(User::factory()->withPermissions(['sessions.view'])->create())
            ->get(route('sessions.dashboard', ['period' => 7]))
            ->assertOk()
            ->assertSee('href="'.e($expected).'"', false);
    }

    public function test_the_users_figure_only_links_for_who_may_list_users(): void
    {
        $this->actingAs(User::factory()->withPermissions(['sessions.view'])->create())
            ->get(route('sessions.dashboard'))
            ->assertOk()
            ->assertDontSee('href="'.e(route('users.index')).'"', false);
    }

    public function test_it_is_forbidden_without_the_session_permission(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('sessions.dashboard'))
            ->assertForbidden();
    }
}
