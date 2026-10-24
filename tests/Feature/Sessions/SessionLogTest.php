<?php

namespace Basics13\Tests\Feature\Sessions;

use LogicException;
use Carbon\CarbonImmutable;
use Basics13\Models\SessionLog;
use Basics13\Tests\Feature\FeatureTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SessionLogTest extends FeatureTestCase
{
    use RefreshDatabase;

    public function test_the_logout_is_stamped_once_on_the_row_of_its_login(): void
    {
        $log = SessionLog::factory()->login()->create();

        $log->logged_out_at = CarbonImmutable::now();
        $log->save();

        $this->assertNotNull($log->refresh()->logged_out_at);
    }

    public function test_changing_any_other_field_is_refused_and_the_row_stays(): void
    {
        $log = SessionLog::factory()->login()->create(['login' => 'ane@example.com']);

        try {
            $log->login = 'changed@example.com';
            $log->save();
            $this->fail('Updating another field must throw.');
        } catch (LogicException) {
            $this->assertSame('ane@example.com', $log->refresh()->login);
        }
    }

    public function test_stamping_the_logout_twice_is_refused_and_the_row_stays(): void
    {
        $log = SessionLog::factory()->login()->create();
        $log->logged_out_at = CarbonImmutable::now()->subMinute();
        $log->save();
        $firstLogout = $log->refresh()->logged_out_at;

        try {
            $log->logged_out_at = CarbonImmutable::now();
            $log->save();
            $this->fail('Stamping the logout twice must throw.');
        } catch (LogicException) {
            $this->assertEquals($firstLogout, $log->refresh()->logged_out_at);
        }
    }

    public function test_deleting_a_row_is_refused_and_the_row_stays(): void
    {
        $log = SessionLog::factory()->create();

        try {
            $log->delete();
            $this->fail('Deleting must throw.');
        } catch (LogicException) {
            $this->assertDatabaseHas('BAS_sessions', ['id' => $log->id]);
        }
    }

    public function test_a_login_longer_than_the_column_is_stored_truncated(): void
    {
        $log = SessionLog::factory()->create(['login' => str_repeat('a', 1000)]);

        $this->assertSame(255, mb_strlen($log->refresh()->login));
    }

    public function test_a_user_agent_longer_than_the_column_is_stored_truncated(): void
    {
        $log = SessionLog::factory()->create(['user_agent' => str_repeat('b', 1000)]);

        $this->assertSame(512, mb_strlen($log->refresh()->user_agent));
    }

    public function test_the_user_is_found_even_in_the_trash(): void
    {
        $log = SessionLog::factory()->login()->create();
        $log->user?->delete();

        $this->assertNotNull($log->refresh()->user);
    }
}
