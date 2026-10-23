<?php

namespace Basics13\Tests\Browser\Sessions;

use Basics13\Models\User;
use Laravel\Dusk\Browser;
use Basics13\Models\SessionLog;
use Basics13\Support\Testing\DuskTestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;

/**
 * The administration chart draws its two series, each in the colour of its own token class.
 */
class SessionDashboardChartTest extends DuskTestCase
{
    use DatabaseMigrations;

    private const STROKES = "return Array.from(document.querySelectorAll('[data-test=\"admin-dashboard-chart\"] .apexcharts-series path.apexcharts-line, [data-test=\"admin-dashboard-chart\"] .apexcharts-series path.apexcharts-area')).map((path) => path.getAttribute('stroke')).filter((stroke) => stroke && stroke !== 'none');";

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
