<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\ServiceAlert;
use App\Services\SmsService;
use App\Services\WebPushDelivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NotificationPreferencesController extends Controller
{
    public function edit(Request $request, WebPushDelivery $push): View
    {
        return view('notification-preferences', [
            'user' => $request->user(),
            'events' => ServiceAlert::cases(),
            'pushConfigured' => $push->configured(),
        ]);
    }

    public function update(Request $request, SmsService $sms): RedirectResponse
    {
        $keys = implode(',', array_column(ServiceAlert::cases(), 'value'));
        $smsKeys = implode(',', array_map(fn (ServiceAlert $event): string => $event->value,
            array_filter(ServiceAlert::cases(), fn (ServiceAlert $event): bool => $event->supportsSms())));
        $attributes = $request->validate([
            'sms_consent' => ['sometimes', 'accepted'],
            'sms_phone' => ['nullable', 'string', 'max:30'],
            'message_preview' => ['sometimes', 'accepted'],
            'email' => ['sometimes', "array:{$keys}"], 'email.*' => ['accepted'],
            'sms' => ['sometimes', "array:{$smsKeys}"], 'sms.*' => ['accepted'],
            'push' => ['sometimes', "array:{$keys}"], 'push.*' => ['accepted'],
        ]);

        DB::transaction(function () use ($request, $attributes, $sms): void {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $smsAllowed = isset($attributes['sms_consent']);
            $phone = $sms->phone($attributes['sms_phone'] ?? $user->phone);
            if ($smsAllowed && ! $phone) {
                throw ValidationException::withMessages(['sms_phone' => __('alerts.invalid_phone')]);
            }

            $preferences = ['message_preview' => isset($attributes['message_preview'])];
            foreach (['email', 'sms', 'push'] as $channel) {
                foreach (ServiceAlert::cases() as $event) {
                    $preferences[$channel][$event->value] = isset($attributes[$channel][$event->value]);
                }
            }
            $user->forceFill(['communication_preferences' => $preferences]);
            if ($smsAllowed) {
                $user->phone = '+'.$phone;
            }
            $user->save();
            $user->recordOptionalConsent('sms', $smsAllowed);
        });

        return back()->with('status', __('alerts.saved'));
    }

    public function subscribe(Request $request, WebPushDelivery $push): Response
    {
        abort_unless($push->configured(), 503);
        $attributes = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:2048'],
            'keys' => ['required', 'array:p256dh,auth'],
            'keys.p256dh' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{87}$/D'],
            'keys.auth' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{22}$/D'],
        ]);
        if (! $push->audience($attributes['endpoint'])) {
            throw ValidationException::withMessages(['endpoint' => __('alerts.push_error')]);
        }
        DB::transaction(function () use ($request, $attributes): void {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            DB::table('push_subscriptions')->upsert([[
                'user_id' => $user->id, 'endpoint_hash' => hash('sha256', $attributes['endpoint']),
                'endpoint' => encrypt($attributes['endpoint']), 'public_key' => $attributes['keys']['p256dh'],
                'auth_secret' => $attributes['keys']['auth'], 'created_at' => now(), 'updated_at' => now(),
            ]], ['endpoint_hash'], ['user_id', 'endpoint', 'public_key', 'auth_secret', 'updated_at']);
            $user->recordOptionalConsent('push', true);
        });

        return response()->noContent();
    }

    public function unsubscribe(Request $request): Response
    {
        DB::transaction(function () use ($request): void {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            DB::table('push_subscriptions')->where('user_id', $user->id)->delete();
            $user->recordOptionalConsent('push', false);
        });

        return response()->noContent();
    }
}
