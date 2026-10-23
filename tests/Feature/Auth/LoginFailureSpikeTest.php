<?php

namespace Basics13\Tests\Feature\Auth;

use RuntimeException;
use Basics13\Models\Setting;
use Basics13\Auth\LoginAudit;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Basics13\Mail\LoginFailureSpikeMail;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

class LoginFailureSpikeTest extends AuthFeatureTestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Exceptions::fake();
        Mail::fake();
        Setting::query()->where('package', 'basics13')->where('variable', 'alert_email')->update(['value' => 'security@example.com']);
    }

    public function test_the_eleventh_failure_reports_once_and_mails_once(): void
    {
        $this->failSignIns(11);

        Exceptions::assertReportedCount(1);
        Exceptions::assertReported(static fn (RuntimeException $exception): bool => str_contains($exception->getMessage(), '11 failures'));
        Mail::assertSent(LoginFailureSpikeMail::class, static fn (LoginFailureSpikeMail $mail): bool => $mail->hasTo('security@example.com')
            && $mail->failures === 11 && $mail->logins === 11 && $mail->ips === 11);
        Mail::assertSentCount(1);
    }

    public function test_more_failures_in_the_window_do_not_alert_again(): void
    {
        $this->failSignIns(15);

        Exceptions::assertReportedCount(1);
        Mail::assertSentCount(1);
    }

    public function test_ten_failures_raise_nothing(): void
    {
        $this->failSignIns(10);

        Exceptions::assertNothingReported();
        Mail::assertNothingSent();
    }

    public function test_failures_older_than_the_window_do_not_count(): void
    {
        $this->failSignIns(6);
        $this->travel(LoginAudit::ALERT_MINUTES + 1)->minutes();
        $this->failSignIns(5, from: 6);

        Exceptions::assertNothingReported();
    }

    public function test_after_the_window_another_alert_can_follow(): void
    {
        $this->failSignIns(11);
        $this->travel(LoginAudit::ALERT_MINUTES + 1)->minutes();
        $this->failSignIns(11, from: 11);

        Exceptions::assertReportedCount(2);
        Mail::assertSentCount(2);
    }

    public function test_an_alert_already_raised_by_another_request_holds_this_one_back(): void
    {
        Cache::put(LoginAudit::ALERT_KEY, true, LoginAudit::ALERT_MINUTES * 60);

        $this->failSignIns(11);

        Exceptions::assertNothingReported();
        Mail::assertNothingSent();
    }

    public function test_attempts_rejected_by_a_lock_count_as_failures(): void
    {
        // Three failures lock the login (the lockout row is not a failure); eight rejections follow.
        foreach (range(1, 11) as $attempt) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.'.$attempt])
                ->post(route('login.store'), ['email' => 'target@example.com', 'password' => 'wrong']);
        }

        Exceptions::assertReportedCount(1);
        Mail::assertSent(LoginFailureSpikeMail::class, static fn (LoginFailureSpikeMail $mail): bool => $mail->failures === 11 && $mail->logins === 1);
    }

    public function test_without_an_alert_email_it_only_reports(): void
    {
        Setting::query()->where('package', 'basics13')->where('variable', 'alert_email')->update(['value' => '']);

        $this->failSignIns(11);

        Exceptions::assertReportedCount(1);
        Mail::assertNothingSent();
    }

    public function test_the_mail_holds_counts_and_a_link_but_no_login_or_ip(): void
    {
        $this->failSignIns(11);

        Mail::assertSent(LoginFailureSpikeMail::class, function (LoginFailureSpikeMail $mail): bool {
            $html = $mail->render();

            $this->assertStringContainsString(route('sessions.index'), $html);
            $this->assertStringNotContainsString('spike1@example.com', $html);
            $this->assertStringNotContainsString('10.0.0.1', $html);

            return true;
        });
    }

    /**
     * Failed sign-ins, each with its own login and IP, so neither the lock nor the IP cap stops
     * them.
     */
    private function failSignIns(int $count, int $from = 0): void
    {
        foreach (range($from + 1, $from + $count) as $attempt) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.'.$attempt])
                ->post(route('login.store'), ['email' => "spike{$attempt}@example.com", 'password' => 'wrong']);
        }
    }
}
