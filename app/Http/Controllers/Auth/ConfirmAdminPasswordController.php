<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ConfirmAdminPasswordController extends Controller
{
    public function create(Request $request): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return view('auth.confirm-admin-password');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $attributes = $request->validate(['password' => ['required', 'string']]);
        if (! Hash::check($attributes['password'], $request->user()->password)) {
            return back()->withErrors(['password' => 'Incorrect password. Please try again.']);
        }
        $request->session()->put('auth.password_confirmed_at', now()->timestamp);
        $returnRoute = $request->session()->pull('admin_financial_return', 'admin.dashboard');

        return to_route($returnRoute)->with('status', 'Password confirmed. Review the details and submit the action again.');
    }
}
