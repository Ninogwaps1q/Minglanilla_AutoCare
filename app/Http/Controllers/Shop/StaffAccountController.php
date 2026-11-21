<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StaffAccountController extends Controller
{
    public function index(Request $request): View
    {
        $shop = $request->user()->shops()->firstOrFail();

        return view('shop.staff.index', ['shop' => $shop, 'staff' => User::query()->where('role', 'shop_staff')->where('staff_shop_id', $shop->id)->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'staff_role' => ['required', Rule::in(['receptionist', 'technician', 'cashier'])],
        ]);
        $shop = $request->user()->shops()->firstOrFail();
        $staff = User::query()->create([...$attributes, 'role' => 'shop_staff', 'staff_shop_id' => $shop->id, 'password' => Str::random(64), 'status' => 'invited']);
        Password::sendResetLink(['email' => $staff->email]);

        return back()->with('status', 'Staff invitation created. A password setup link was emailed.');
    }

    public function update(Request $request, User $staffAccount): RedirectResponse
    {
        $shop = $request->user()->shops()->firstOrFail();
        abort_unless($staffAccount->role === 'shop_staff' && $staffAccount->staff_shop_id === $shop->id, 404);
        $attributes = $request->validate(['staff_role' => ['required', Rule::in(['receptionist', 'technician', 'cashier'])], 'status' => ['required', Rule::in(['active', 'suspended'])]]);
        abort_if($attributes['status'] === 'active' && ! $staffAccount->hasVerifiedEmail(), 422, 'The staff member must finish the email invitation first.');
        $staffAccount->update($attributes);

        return back()->with('status', 'Staff access updated.');
    }

    public function resend(Request $request, User $staffAccount): RedirectResponse
    {
        $shop = $request->user()->shops()->firstOrFail();
        abort_unless($staffAccount->role === 'shop_staff' && $staffAccount->staff_shop_id === $shop->id && $staffAccount->status === 'invited', 404);
        Password::sendResetLink(['email' => $staffAccount->email]);

        return back()->with('status', 'A new password setup link was emailed.');
    }
}
