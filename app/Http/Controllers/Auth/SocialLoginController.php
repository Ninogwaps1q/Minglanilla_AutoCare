<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

class SocialLoginController extends Controller
{
    public function redirect(Request $request, string $provider): RedirectResponse
    {
        if (! config("services.{$provider}.client_id") || ! config("services.{$provider}.client_secret")) {
            return to_route('login')->withErrors([
                'social' => ucfirst($provider).' sign-in is not configured yet. Please use your email and password.',
            ]);
        }

        $configuredUrl = rtrim((string) config('app.url'), '/');
        $configuredHost = strtolower((string) parse_url($configuredUrl, PHP_URL_HOST));
        $loopbackHosts = ['localhost', '127.0.0.1', '[::1]'];
        $usesLoopback = in_array($configuredHost, $loopbackHosts, true);

        if ($usesLoopback && ! in_array($request->getHost(), $loopbackHosts, true)
            && ! $this->isServerComputer($request)) {
            return to_route('login')->withErrors([
                'social' => ucfirst($provider).' sign-in is currently available only on the server computer. To sign in from this device, the administrator must configure a shared HTTPS site address. You can still use your email and password.',
            ]);
        }

        if ($provider === 'google' && ! $usesLoopback
            && (parse_url($configuredUrl, PHP_URL_SCHEME) !== 'https'
                || filter_var(trim($configuredHost, '[]'), FILTER_VALIDATE_IP))) {
            return to_route('login')->withErrors([
                'social' => 'Google sign-in needs an HTTPS domain registered with Google. A local network IP address cannot be used. Please use your email and password until the administrator finishes setup.',
            ]);
        }

        if ($request->getSchemeAndHttpHost() !== $configuredUrl) {
            return redirect()->away($configuredUrl.route('social.redirect', $provider, false));
        }

        return Socialite::driver($provider)->redirect();
    }

    private function isServerComputer(Request $request): bool
    {
        $clientIp = $request->ip();

        if ($clientIp === null) {
            return false;
        }

        if (in_array($clientIp, ['127.0.0.1', '::1', $request->server('SERVER_ADDR')], true)) {
            return true;
        }

        $hostname = gethostname();

        return $hostname !== false && in_array($clientIp, gethostbynamel($hostname) ?: [], true);
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        try {
            $providerUser = Socialite::driver($provider)->user();
        } catch (InvalidStateException $exception) {
            return to_route('login')->withErrors([
                'social' => 'Your sign-in session expired or the site address changed. Open '.rtrim((string) config('app.url'), '/').' and try again with cookies enabled.',
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return to_route('login')->withErrors([
                'social' => 'We could not complete '.ucfirst($provider).' sign-in. Please try again or use your email and password.',
            ]);
        }

        $providerUserId = (string) $providerUser->getId();

        if ($providerUserId === '' || strlen($providerUserId) > 255) {
            return to_route('login')->withErrors([
                'social' => 'Your sign-in provider did not return a valid account. Please try again.',
            ]);
        }

        $socialAccount = SocialAccount::query()
            ->with('user')
            ->where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first();

        if ($socialAccount) {
            return $this->signIn($request, $socialAccount->user);
        }

        $email = filter_var($providerUser->getEmail(), FILTER_VALIDATE_EMAIL);

        if (! $email) {
            return to_route('login')->withErrors([
                'social' => ucfirst($provider).' did not share an email address. Please allow email access or create an account with email and password.',
            ]);
        }

        $email = Str::lower($email);

        if (User::query()->where('email', $email)->exists()) {
            $request->session()->put('pending_social_link', [
                'provider' => $provider,
                'provider_user_id' => $providerUserId,
                'email' => $email,
                'created_at' => now()->timestamp,
            ]);

            return to_route('login')->withErrors([
                'social' => 'An account already uses this email address. Sign in with your password and confirm the connection below to use '.ucfirst($provider).' next time.',
            ])->withInput(['email' => $email]);
        }

        try {
            $user = DB::transaction(function () use ($provider, $providerUser, $providerUserId, $email): User {
                $user = User::create([
                    'name' => Str::limit(trim((string) $providerUser->getName()) ?: Str::before($email, '@'), 255, ''),
                    'email' => $email,
                    'password' => Str::random(64),
                    'role' => 'customer',
                    'status' => 'active',
                ]);

                if ($provider === 'google' && ($providerUser->getRaw()['email_verified'] ?? false) === true) {
                    $user->markEmailAsVerified();
                }

                $user->socialAccounts()->create([
                    'provider' => $provider,
                    'provider_user_id' => $providerUserId,
                ]);

                return $user;
            });
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }

            return to_route('login')->withErrors([
                'social' => 'This email or social account is already in use. Please sign in with your existing account.',
            ]);
        }

        event(new Registered($user));

        return $this->signIn($request, $user);
    }

    private function signIn(Request $request, User $user): RedirectResponse
    {
        if ($user->status !== 'active') {
            return to_route('login')->withErrors([
                'social' => 'This account is unavailable. Please contact support.',
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        if (! $user->hasVerifiedEmail()) {
            return to_route('verification.notice');
        }

        $dashboardRoute = match ($user->role) {
            'shop_owner' => 'shop.dashboard',
            'admin' => 'admin.dashboard',
            default => 'dashboard',
        };

        return redirect()->intended(route($dashboardRoute));
    }
}
