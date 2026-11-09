<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\SocialAccount;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        if (! Auth::attempt([...$request->safe()->only('email', 'password'), 'status' => 'active'], $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'Incorrect email address or password. Please check your details and try again.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        $linkedProvider = $this->completePendingSocialLink($request);

        if (! $request->user()->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        $dashboardRoute = match ($request->user()->role) {
            'shop_owner' => 'shop.dashboard',
            'admin' => 'admin.dashboard',
            default => 'dashboard',
        };

        $response = redirect()->intended(route($dashboardRoute));

        return $linkedProvider
            ? $response->with('status', ucfirst($linkedProvider).' sign-in is now connected to your account.')
            : $response;
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function completePendingSocialLink(LoginRequest $request): ?string
    {
        $pending = $request->session()->pull('pending_social_link');

        if (! $request->boolean('connect_social')
            || ! is_array($pending)
            || ! in_array($pending['provider'] ?? null, ['google', 'facebook'], true)
            || ! is_string($pending['provider_user_id'] ?? null)
            || ($pending['provider_user_id'] ?? '') === ''
            || ! is_string($pending['email'] ?? null)
            || ! is_int($pending['created_at'] ?? null)
            || $pending['created_at'] < now()->subMinutes(10)->timestamp
            || ! hash_equals(Str::lower($request->user()->email), $pending['email'])) {
            return null;
        }

        if ($request->user()->socialAccounts()->where('provider', $pending['provider'])->exists()) {
            return null;
        }

        try {
            SocialAccount::create([
                'user_id' => $request->user()->id,
                'provider' => $pending['provider'],
                'provider_user_id' => $pending['provider_user_id'],
            ]);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }

            return null;
        }

        return $pending['provider'];
    }
}
