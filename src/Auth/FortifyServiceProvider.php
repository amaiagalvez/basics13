<?php

namespace Basics13\Auth;

use LogicException;
use Livewire\Livewire;
use Illuminate\Http\Request;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Features;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Basics13\Actions\Fortify\CreateNewUser;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;
use Basics13\Actions\Fortify\ResetUserPassword;
use Basics13\Http\Middleware\RequirePasswordForLivewire;

class FortifyServiceProvider extends ServiceProvider
{
    private const MANAGED_FEATURES = [
        'registration',
        'email-verification',
        'two-factor-authentication',
        'passkeys',
    ];

    public function register(): void
    {
        $this->configurePasskeySettings();
        $this->configureFeatures();
    }

    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
        $this->configureActiveUsers();
        $this->registerLoginAudit();

        if ($this->featureEnabled('password_confirmation')) {
            Livewire::addPersistentMiddleware([RequirePasswordForLivewire::class]);
        }
    }

    private function configurePasskeySettings(): void
    {
        $packageSettings = config('basics13.auth.passkeys', []);
        $hostSettings = config('fortify.passkeys', []);

        if (! is_array($packageSettings) || ! is_array($hostSettings)) {
            throw new LogicException('Passkey settings must be configured as arrays.');
        }

        config(['fortify.passkeys' => array_replace_recursive($packageSettings, $hostSettings)]);
    }

    private function configureFeatures(): void
    {
        $configuredFeatures = config('fortify.features', []);

        if (! is_array($configuredFeatures)) {
            throw new LogicException('Fortify features must be configured as an array.');
        }

        foreach ($configuredFeatures as $feature) {
            if (! is_string($feature)) {
                throw new LogicException('Fortify feature names must be strings.');
            }
        }

        $features = array_values(array_filter(
            $configuredFeatures,
            static fn (string $feature): bool => ! in_array($feature, self::MANAGED_FEATURES, true),
        ));

        if ($this->featureEnabled('registration')) {
            $features[] = Features::registration();
        }

        if ($this->featureEnabled('email_verification')) {
            $features[] = Features::emailVerification();
        }

        if ($this->featureEnabled('two_factor_authentication')) {
            $options = $this->featureOptions('two-factor-authentication');
            $options['confirm'] ??= true;
            $options['confirmPassword'] = $this->featureEnabled('password_confirmation');
            $features[] = Features::twoFactorAuthentication($options);
        }

        if ($this->featureEnabled('passkeys')) {
            $options = $this->featureOptions('passkeys');
            $options['confirmPassword'] = $this->featureEnabled('password_confirmation');
            $features[] = Features::passkeys($options);
        }

        config(['fortify.features' => $features]);
    }

    /**
     * @return array<string, mixed>
     */
    private function featureOptions(string $feature): array
    {
        $options = config("fortify-options.{$feature}", []);

        if (! is_array($options)) {
            throw new LogicException("Fortify options for [{$feature}] must be configured as an array.");
        }

        return $options;
    }

    private function featureEnabled(string $feature): bool
    {
        $enabled = config("basics13.auth.features.{$feature}");

        if (! is_bool($enabled)) {
            throw new LogicException("The basics13 auth feature [{$feature}] must be configured as a boolean.");
        }

        return $enabled;
    }

    private function configureActiveUsers(): void
    {
        Fortify::authenticateUsing(function (Request $request): ?Authenticatable {
            app(LoginAudit::class)->rejectIfLocked($request);

            $username = Fortify::username();
            $userModel = $this->userModelClass();
            $user = $userModel::query()
                ->where($username, $request->input($username))
                ->where('active', true)
                ->first();
            $provider = Auth::guard(config()->string('fortify.guard'))->getProvider();
            $credentials = $request->only('password');

            if (! $user instanceof Authenticatable || ! $provider->validateCredentials($user, $credentials)) {
                return null;
            }

            if (config('hashing.rehash_on_login', true)) {
                $provider->rehashPasswordIfRequired($user, $credentials);
            }

            return $user;
        });

        Event::listen(Login::class, function (Login $event): void {
            $user = $event->user;

            if (! $user instanceof Model || $user->newQuery()
                ->whereKey($user->getAuthIdentifier())
                ->where('active', true)
                ->exists()) {
                return;
            }

            $guard = Auth::guard($event->guard);
            $guard->setUser($user);
            $guard->logout();

            if (request()->hasSession()) {
                request()->session()->invalidate();
                request()->session()->regenerateToken();
            }

            throw ValidationException::withMessages([
                Fortify::username() => [__('auth.failed')],
            ]);
        });
    }

    /**
     * The access register listeners. Registered after the active-users one, which throws first
     * for an inactive account; LoginAudit does not rely on that order and skips them itself.
     */
    private function registerLoginAudit(): void
    {
        Event::listen(Login::class, [LoginAudit::class, 'handleLogin']);
        Event::listen(Failed::class, [LoginAudit::class, 'handleFailed']);
        Event::listen(Logout::class, [LoginAudit::class, 'handleLogout']);
    }

    /**
     * @return class-string<Model&Authenticatable>
     */
    private function userModelClass(): string
    {
        $userModel = config('auth.providers.users.model');

        if (! is_string($userModel) ||
            ! is_subclass_of($userModel, Model::class) ||
            ! is_subclass_of($userModel, Authenticatable::class)) {
            throw new LogicException('The configured users model must be an Eloquent authenticatable model.');
        }

        return $userModel;
    }

    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        if ($this->featureEnabled('registration')) {
            Fortify::createUsersUsing(CreateNewUser::class);
        }
    }

    private function configureViews(): void
    {
        Fortify::loginView(fn () => view('basics13::pages.auth.login'));
        Fortify::resetPasswordView(fn () => view('basics13::pages.auth.reset-password'));
        Fortify::requestPasswordResetLinkView(fn () => view('basics13::pages.auth.forgot-password'));

        if ($this->featureEnabled('email_verification')) {
            Fortify::verifyEmailView(fn () => view('basics13::pages.auth.verify-email'));
        }

        if ($this->featureEnabled('two_factor_authentication')) {
            Fortify::twoFactorChallengeView(fn () => view('basics13::pages.auth.two-factor-challenge'));
        }

        if ($this->featureEnabled('password_confirmation')) {
            Fortify::confirmPasswordView(fn () => view('basics13::pages.auth.confirm-password'));
        }

        if ($this->featureEnabled('registration')) {
            Fortify::registerView(fn () => view('basics13::pages.auth.register'));
        }
    }

    private function configureRateLimiting(): void
    {
        // A cap per IP against password spraying; the lock of each login lives in LoginAudit.
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(10)->by((string) $request->ip());
        });

        if ($this->featureEnabled('two_factor_authentication')) {
            RateLimiter::for('two-factor', function (Request $request) {
                return Limit::perMinute(5)->by($request->session()->get('login.id'));
            });
        }

        if ($this->featureEnabled('passkeys')) {
            RateLimiter::for('passkeys', function (Request $request) {
                $credentialId = $request->input('credential.id');
                $throttleIdentifier = is_string($credentialId) && $credentialId !== ''
                    ? $credentialId
                    : $request->session()->getId();

                return Limit::perMinute(10)->by($throttleIdentifier.'|'.$request->ip());
            });
        }
    }
}
