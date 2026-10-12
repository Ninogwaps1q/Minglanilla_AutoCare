<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        $dashboardRoute = match ($request->user()->role) {
            'shop_owner' => 'shop.dashboard',
            'admin' => 'admin.dashboard',
            default => 'dashboard',
        };

        return redirect()->intended(route($dashboardRoute));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
