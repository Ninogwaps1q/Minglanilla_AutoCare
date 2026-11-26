<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\PrivacyRequest;
use App\Models\User;
use App\Services\AnonymizeCustomer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PrivacyController extends Controller
{
    public function location(Request $request): Response
    {
        $attributes = $request->validate(['allowed' => ['required', 'boolean']]);
        $allowed = (bool) $attributes['allowed'];
        if ($request->user()) {
            DB::transaction(function () use ($request, $allowed): void {
                User::query()->lockForUpdate()->findOrFail($request->user()->id)->recordOptionalConsent('location', $allowed);
            });
        } else {
            $request->session()->put('location_consent', ['allowed' => $allowed, 'version' => config('privacy.version'), 'at' => now()->toISOString()]);
        }

        return response()->noContent();
    }

    public function store(Request $request): RedirectResponse
    {
        $attributes = $request->validate(['type' => ['required', 'in:download,delete']]);
        DB::transaction(function () use ($request, $attributes): void {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            PrivacyRequest::query()->firstOrCreate(['user_id' => $user->id, 'type' => $attributes['type'], 'status' => 'pending']);
        });

        return back()->with('status', __('privacy.request_recorded'));
    }

    public function queue(): View
    {
        return view('admin.privacy-requests', ['privacyRequests' => PrivacyRequest::query()->with('user')
            ->orderByRaw("case when status = 'pending' then 0 else 1 end")->latest()->paginate(20)]);
    }

    public function resolve(Request $request, PrivacyRequest $privacyRequest, AnonymizeCustomer $anonymize): RedirectResponse
    {
        $attributes = $request->validate(['status' => ['required', 'in:completed,declined'], 'reason' => ['required', 'string', 'max:500']]);
        DB::transaction(function () use ($request, $privacyRequest, $attributes, $anonymize): void {
            $item = PrivacyRequest::query()->lockForUpdate()->findOrFail($privacyRequest->id);
            abort_unless($item->status === 'pending', 409);
            if ($item->type === 'delete' && $attributes['status'] === 'completed') {
                $anonymize->handle(User::withTrashed()->lockForUpdate()->findOrFail($item->user_id));
            }
            $item->update(['status' => $attributes['status']]);
            ActivityLog::query()->create([
                'user_id' => $request->user()->id, 'action' => 'resolved_privacy_request', 'module' => 'privacy',
                'description' => "Request #{$item->id} {$item->status}. Reason: ".$attributes['reason'],
                'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
            ]);
        });

        return back()->with('status', 'Privacy request updated.');
    }
}
