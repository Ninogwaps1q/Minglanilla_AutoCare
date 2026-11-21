<?php

namespace App;

use App\Models\User;
use App\Notifications\ImportantEventMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

class AdminTwoFactor
{
    public function begin(Request $request, User $user): void
    {
        $code = (string) random_int(100000, 999999);
        $request->session()->put('admin_two_factor', ['user_id' => $user->id, 'code_hash' => Hash::make($code), 'expires_at' => now()->addMinutes(10)->timestamp]);
        $request->session()->forget('admin_two_factor_verified_at');
        Notification::sendNow($user, new ImportantEventMail('Admin sign-in code', "Your six-digit admin sign-in code is {$code}. It expires in 10 minutes.", route('admin.two-factor.create')));
    }

    public function verify(Request $request, string $code): bool
    {
        $pending = $request->session()->get('admin_two_factor');
        if (! is_array($pending) || ($pending['user_id'] ?? null) !== $request->user()?->id
            || ($pending['expires_at'] ?? 0) < now()->timestamp || ! Hash::check($code, $pending['code_hash'] ?? '')) {
            return false;
        }
        $request->session()->forget('admin_two_factor');
        $request->session()->put('admin_two_factor_verified_at', now()->timestamp);

        return true;
    }

    public function isPending(Request $request): bool
    {
        return $request->user()?->role === 'admin' && is_array($request->session()->get('admin_two_factor'));
    }
}
