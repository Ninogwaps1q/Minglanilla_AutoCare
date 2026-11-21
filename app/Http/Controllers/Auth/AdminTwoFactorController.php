<?php

namespace App\Http\Controllers\Auth;

use App\AdminTwoFactor;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminTwoFactorController extends Controller
{
    public function create(Request $request, AdminTwoFactor $twoFactor): View|RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        if (! $twoFactor->isPending($request)) {
            return to_route('admin.dashboard');
        }

        return view('auth.admin-two-factor');
    }

    public function store(Request $request, AdminTwoFactor $twoFactor): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $attributes = $request->validate(['code' => ['required', 'digits:6']]);
        if (! $twoFactor->verify($request, $attributes['code'])) {
            return back()->withErrors(['code' => 'The code is invalid or expired. Request a new code and try again.']);
        }

        return redirect()->intended(route('admin.dashboard'));
    }

    public function resend(Request $request, AdminTwoFactor $twoFactor): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin() && $twoFactor->isPending($request), 403);
        $twoFactor->begin($request, $request->user());

        return back()->with('status', 'A new code has been emailed to you.');
    }
}
