<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCustomerProfileRequest;
use App\Models\Barangay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('customer.profile.edit', [
            'user' => request()->user(),
            'barangays' => Barangay::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateCustomerProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $attributes = $request->safe()->except('profile_photo');
        $previousPhoto = null;

        if ($request->hasFile('profile_photo')) {
            $previousPhoto = $user->profile_photo;
            $attributes['profile_photo'] = $request->file('profile_photo')->store('profile-photos', 'public');
        }

        $user->update($attributes);
        if ($previousPhoto !== null) {
            Storage::disk('public')->delete($previousPhoto);
        }

        return to_route('customer.profile.edit')->with('status', 'Your profile has been updated.');
    }
}
