<?php

namespace Basics13\Auth;

use Throwable;
use RuntimeException;
use Illuminate\Support\Str;
use Basics13\Models\Setting;
use Illuminate\Http\Request;
use Laravel\Fortify\Fortify;
use Basics13\Enums\DeviceType;
use Basics13\Models\SessionLog;
use Basics13\Enums\SessionEvent;
use Basics13\Mail\ManagedMailer;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Basics13\Enums\LoginFailureReason;
use Illuminate\Database\Eloquent\Model;
use Basics13\Concerns\ResolvesUserModel;
use Basics13\Mail\LoginFailureSpikeMail;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * The writer of the access register. Every successful login, failed attempt and logout leaves
 * its row; an impersonation leaves a login row marked with its impersonator, closed when it is
 * left. Remember-cookie restorations and inactive accounts kicked out by the active-users
 * listener leave none. The password is never read: only the login field is.
 *
 * It also locks a login after `max_login_attempts` consecutive failures for `lockout_minutes`
 * full minutes. The counter starts its timer at the first failure, so the lock has its own key
 * with the full duration; while it exists even the right password is rejected.
 *
 * When failures spike (more than ALERT_THRESHOLD in ALERT_MINUTES, locked rejections included)
 * it reports an incident and mails `alert_email`, at most once per window.
 */
class LoginAudit
{
    use ResolvesUserModel;

    /**
     * The session attribute linking a login row to its logout: the Laravel session id changes at
     * login (Fortify regenerates it), while this uuid migrates with the session data.
     */
    public const SESSION_KEY = 'basics13.session_key';

    /**
     * The impersonator's own session key, put aside while the impersonation row is the open one.
     */
    public const IMPERSONATOR_SESSION_KEY = 'basics13.impersonator_session_key';

    /**
     * The cache prefix of a locked login; the value is the timestamp the lock ends at.
     */
    private const LOCK_PREFIX = 'login-locked|';

    /**
     * More failures than this within ALERT_MINUTES raise the alert.
     */
    public const ALERT_THRESHOLD = 10;

    public const ALERT_MINUTES = 5;

    /**
     * The cache key that holds back a second alert for ALERT_MINUTES.
     */
    public const ALERT_KEY = 'basics13.login-alert';

    /**
     * Record a successful login and link the row to the session, unless the sign-in comes from a
     * remember cookie or belongs to an inactive account. Starting an impersonation records a row
     * marked with the impersonator; going back to the impersonator closes it instead.
     */
    public function handleLogin(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof Model) {
            return;
        }

        if (Auth::guard($event->guard)->viaRemember()) {
            return;
        }

        $request = request();

        if (! $request->hasSession()) {
            return;
        }

        $session = $request->session();

        $impersonatorId = $session->get('impersonator_id');

        if ($impersonatorId !== null && (int) $impersonatorId === (int) $user->getAuthIdentifier()) {
            $this->closeImpersonation($session);

            return;
        }

        // Inactive accounts are kicked out by the active-users listener; do not record them here
        // either, whatever order the listeners run in.
        if (! $user->newQuery()->whereKey($user->getAuthIdentifier())->where('active', true)->exists()) {
            return;
        }

        $key = (string) Str::uuid();
        $login = $this->loginInput($request, $user);

        SessionLog::create([
            'user_id' => $user->getAuthIdentifier(),
            'impersonator_id' => $impersonatorId,
            'event' => SessionEvent::Login,
            'failure_reason' => null,
            'login' => $login,
            'ip' => (string) $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'device_type' => DeviceType::fromUserAgent((string) $request->userAgent()),
            'session_key' => $key,
        ]);

        if ($impersonatorId !== null) {
            $session->put(self::IMPERSONATOR_SESSION_KEY, $session->get(self::SESSION_KEY));
            $session->put(self::SESSION_KEY, $key);

            return;
        }

        $session->put(self::SESSION_KEY, $key);
        RateLimiter::clear($this->lockoutKey($login));
    }

    /**
     * Record a failed attempt with its reason and count it towards the lock of its login. The
     * credentials carry the typed password: only the login field is read, never anything else.
     */
    public function handleFailed(Failed $event): void
    {
        $request = request();
        $login = $this->loginInput($request);
        [$userId, $reason] = $this->failureReason($login);

        $this->recordFailure($request, $login, $userId, $reason);

        $maxAttempts = (int) Setting::get('basics13', 'max_login_attempts', 3);
        $lockoutSeconds = (int) Setting::get('basics13', 'lockout_minutes', 15) * 60;
        $key = $this->lockoutKey($login);

        if (RateLimiter::hit($key, $lockoutSeconds) < $maxAttempts) {
            return;
        }

        RateLimiter::clear($key);

        // Only the request that sets the lock writes its row, even with concurrent failures.
        if (! Cache::add(self::LOCK_PREFIX.$key, now()->getTimestamp() + $lockoutSeconds, $lockoutSeconds)) {
            return;
        }

        SessionLog::create([
            'user_id' => $userId,
            'event' => SessionEvent::Lockout,
            'failure_reason' => null,
            'login' => $login,
            'ip' => (string) $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'device_type' => DeviceType::fromUserAgent((string) $request->userAgent()),
            'lockout_minutes' => intdiv($lockoutSeconds, 60),
        ]);
    }

    /**
     * Reject a sign-in on a locked login before the password is checked, recording it as a
     * `locked` failure. The lock is neither counted again nor extended.
     *
     * @throws ValidationException
     */
    public function rejectIfLocked(Request $request): void
    {
        $login = $this->loginInput($request);
        $lockedUntil = Cache::get(self::LOCK_PREFIX.$this->lockoutKey($login));

        if (! is_int($lockedUntil)) {
            return;
        }

        [$userId] = $this->failureReason($login);
        $this->recordFailure($request, $login, $userId, LoginFailureReason::Locked);

        throw ValidationException::withMessages([
            Fortify::username() => [__('basics13::messages.login_locked', [
                'minutes' => max(1, (int) ceil(($lockedUntil - now()->getTimestamp()) / 60)),
            ])],
        ]);
    }

    /**
     * Stamp the logout on the row of this session's login, once, and on the impersonator's when
     * the session ends during an impersonation. Repeating it changes nothing and never fails, and
     * a session without the key (an `actingAs` in a test, a very old one) is simply ignored.
     */
    public function handleLogout(Logout $event): void
    {
        $request = request();

        if (! $request->hasSession()) {
            return;
        }

        $session = $request->session();

        $this->stampLogout($session->get(self::SESSION_KEY));
        $this->stampLogout($session->get(self::IMPERSONATOR_SESSION_KEY));
    }

    /**
     * Leaving an impersonation: its row gets the logout and the impersonator's key is back.
     */
    private function closeImpersonation(Session $session): void
    {
        $this->stampLogout($session->get(self::SESSION_KEY));

        $session->put(self::SESSION_KEY, $session->pull(self::IMPERSONATOR_SESSION_KEY));
    }

    private function stampLogout(mixed $key): void
    {
        if (! is_string($key) || $key === '') {
            return;
        }

        SessionLog::where('session_key', $key)
            ->whereNull('logged_out_at')
            ->update(['logged_out_at' => now()]);
    }

    /**
     * The one key of a login's counter and lock: the typed login normalized, without the IP, so
     * the lock holds from any address and whether or not the account exists.
     */
    private function lockoutKey(string $login): string
    {
        return Str::transliterate(Str::lower($login));
    }

    /**
     * Every failure, counted or rejected by a lock, is written here.
     */
    private function recordFailure(Request $request, string $login, ?int $userId, LoginFailureReason $reason): void
    {
        SessionLog::create([
            'user_id' => $userId,
            'event' => SessionEvent::Failed,
            'failure_reason' => $reason,
            'login' => $login,
            'ip' => (string) $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'device_type' => DeviceType::fromUserAgent((string) $request->userAgent()),
        ]);

        $this->alertOnSpike();
    }

    /**
     * Report the spike and mail it, once per window. The cheap cache check comes first; the
     * `add` decides the race between concurrent failures, so only one request alerts. The mail is
     * sent synchronously (one per window at most and there is no queue worker), and a mail failure
     * is reported without breaking the sign-in response.
     */
    private function alertOnSpike(): void
    {
        if (Cache::has(self::ALERT_KEY)) {
            return;
        }

        $failures = SessionLog::query()
            ->where('event', SessionEvent::Failed->value)
            ->where('created_at', '>=', now()->subMinutes(self::ALERT_MINUTES));

        if (! (clone $failures)->skip(self::ALERT_THRESHOLD)->take(1)->exists()) {
            return;
        }

        if (! Cache::add(self::ALERT_KEY, true, self::ALERT_MINUTES * 60)) {
            return;
        }

        $count = (clone $failures)->count();
        $logins = (clone $failures)->distinct()->count('login');
        $ips = (clone $failures)->distinct()->count('ip');

        report(new RuntimeException(sprintf(
            'Failed sign-ins are spiking: %d failures in the last %d minutes, %d different logins, %d different IP addresses.',
            $count,
            self::ALERT_MINUTES,
            $logins,
            $ips,
        )));

        $email = (string) Setting::get('basics13', 'alert_email', '');

        if ($email === '') {
            return;
        }

        try {
            Mail::mailer(ManagedMailer::prepare())->to($email)->send(new LoginFailureSpikeMail($count, $logins, $ips, self::ALERT_MINUTES));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * What was typed in the login field. A challenge step (2FA, passkey) does not carry it, so
     * the account's own login is the fallback.
     */
    private function loginInput(Request $request, ?Model $user = null): string
    {
        $login = $request->input(Fortify::username());

        if (is_string($login) && trim($login) !== '') {
            return trim($login);
        }

        $fallback = $user?->getAttribute(Fortify::username());

        return is_string($fallback) ? $fallback : '';
    }

    /**
     * Why the attempt failed, looking the typed login up without touching the password.
     *
     * @return array{?int, LoginFailureReason}
     */
    private function failureReason(string $login): array
    {
        $userModel = $this->userModelClass();
        $user = $userModel::query()->where(Fortify::username(), $login)->first();

        if (! $user instanceof Model) {
            return [null, LoginFailureReason::UnknownUser];
        }

        if (! $user->getAttribute('active')) {
            return [$user->getKey(), LoginFailureReason::Inactive];
        }

        return [$user->getKey(), LoginFailureReason::WrongPassword];
    }
}
