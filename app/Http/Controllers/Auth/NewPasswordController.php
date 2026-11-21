<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth.reset-password', ['request' => $request]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['token' => ['required'], 'email' => ['required', 'email'], 'password' => ['required', 'confirmed', Password::defaults()]]);
        $status = \Illuminate\Support\Facades\Password::reset($request->only('email', 'password', 'password_confirmation', 'token'), function (User $user, string $password): void {
            $updates = ['password' => $password, 'remember_token' => Str::random(60)];
            if ($user->role === 'shop_staff' && $user->status === 'invited') {
                $updates['status'] = 'active';
                $updates['email_verified_at'] = now();
            }
            $user->forceFill($updates)->save();
            Auth::logoutOtherDevices($password);
        });

        return $status === \Illuminate\Support\Facades\Password::PASSWORD_RESET ? redirect()->route('login')->with('status', __($status)) : back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }
}
