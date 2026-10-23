<?php

namespace Basics13\Tests\Browser\Sessions;

use Basics13\Models\User;
use Laravel\Dusk\Browser;
use Basics13\Enums\DeviceType;
use Basics13\Models\SessionLog;
use Basics13\Support\Testing\DuskTestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;

/**
 * The administration charts draw each series or slice in the colour of its own token class.
 */
class SessionDashboardChartTest extends DuskTestCase
{
    use DatabaseMigrations;

    private const STROKES = "return Array.from(document.querySelectorAll('[data-test=\"admin-dashboard-chart\"] .apexcharts-series path.apexcharts-line, [data-test=\"admin-dashboard-chart\"] .apexcharts-series path.apexcharts-area')).map((path) => path.getAttribute('stroke')).filter((stroke) => stroke && stroke !== 'none');";

    private const SLICES = "return Array.from(document.querySelectorAll('[data-test=\"admin-dashboard-devices\"] .apexcharts-pie-area')).map((path) => path.getAttribute('fill'));";

    public function test_the_device_donut_draws_each_type_in_its_own_colour(): void
    {
        $user = User::factory()->withPermissions(['sessions.view'])->create();

        foreach (DeviceType::cases() as $device) {
            SessionLog::factory()->login()->create(['user_id' => $user->getKey(), 'device_type' => $device]);
        }

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user)
                ->visit('/sessions/dashboard?period=7')
                ->waitFor('[data-test="admin-dashboard-devices"] .apexcharts-pie-area');

            $this->assertCount(3, array_unique($browser->script(self::SLICES)[0]));
        });
    }

    public function test_the_two_series_are_drawn_in_different_colours(): void
    {
        $user = User::factory()->withPermissions(['sessions.view'])->create();
        SessionLog::factory()->login()->count(2)->create(['user_id' => $user->getKey()]);

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user)
                ->visit('/sessions/dashboard?period=7')
                ->waitFor('[data-test="admin-dashboard-chart"] .apexcharts-series')
                ->assertSeeIn('[data-test="admin-dashboard-chart"]', __('Unique users'));

            $strokes = array_values(array_unique($browser->script(self::STROKES)[0]));

            $this->assertCount(2, $strokes);
        });
    }
}
