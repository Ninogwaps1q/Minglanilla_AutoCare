<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\AppointmentRescheduleRequest;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\Notification;
use App\Models\RepairJob;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AnonymizeCustomer
{
    public function handle(User $customer): void
    {
        if ($customer->role !== 'customer' || $customer->shops()->exists()
            || $customer->appointments()->whereIn('status', ['pending', 'confirmed', 'in_progress'])->exists()
            || $customer->emergencyRequests()->whereIn('status', ['pending', 'accepted', 'en_route'])->exists()
            || RepairJob::query()->where('customer_id', $customer->id)->whereNotIn('status', ['released', 'cancelled'])->exists()) {
            throw ValidationException::withMessages(['reason' => __('extensions.archive.active_work')]);
        }
        if ($customer->anonymized_at) {
            return;
        }

        $email = $customer->email;
        $files = array_filter([$customer->profile_photo]);
        Vehicle::withTrashed()->where('customer_id', $customer->id)->lockForUpdate()->lazyById()->each(function (Vehicle $vehicle) use (&$files): void {
            if ($vehicle->photo) {
                $files[] = $vehicle->photo;
            }
            $vehicle->update(['plate_number' => 'PRIVATE-'.Str::random(20), 'vin' => null, 'photo' => null, 'color' => null]);
            if (! $vehicle->trashed()) {
                $vehicle->delete();
            }
        });
        $appointments = Appointment::withTrashed()->where('customer_id', $customer->id);
        AppointmentRescheduleRequest::query()->whereIn('appointment_id', (clone $appointments)->select('id'))->update(['reason' => null]);
        $appointments->update(['service_address' => null, 'service_barangay_id' => null, 'service_street' => null, 'service_landmark' => null, 'reason' => null]);
        $customer->emergencyRequests()->update(['address' => null, 'barangay_id' => null, 'landmark' => null, 'latitude' => null, 'longitude' => null, 'description' => null]);
        $replacement = __('extensions.archive.removed_content', [], $customer->locale ?? 'en');
        Message::query()->where('sender_id', $customer->id)->update(['message' => $replacement]);
        MessageThread::query()->where('customer_id', $customer->id)->lazyById()->each(
            fn (MessageThread $thread) => Notification::query()->where('type', 'message_received')->where('data->thread_id', $thread->id)->update(['message' => $replacement])
        );
        $customer->socialAccounts()->delete();
        DB::table('sessions')->where('user_id', $customer->id)->delete();
        DB::table('password_reset_tokens')->where('email', $email)->delete();
        DB::table('push_subscriptions')->where('user_id', $customer->id)->delete();
        ActivityLog::query()->where('module', 'user_management')->where('description', 'like', 'Set '.$email.' status to %')
            ->lazyById()->each(fn (ActivityLog $log) => $log->update(['description' => str_replace($email, $replacement, $log->description)]));

        $customer->forceFill([
            'name' => __('extensions.archive.anonymous_name'), 'email' => Str::uuid().'@deleted.invalid',
            'phone' => null, 'profile_photo' => null, 'address' => null, 'landmark' => null, 'barangay_id' => null,
            'remember_token' => null, 'email_verified_at' => null, 'password' => Str::random(64),
            'communication_preferences' => null, 'optional_consents' => null, 'anonymized_at' => now(),
        ])->save();
        if (! $customer->trashed()) {
            $customer->delete();
        }
        DB::afterCommit(function () use ($files, $customer): void {
            if ($files && ! Storage::disk('public')->delete($files)) {
                Log::warning('privacy.photo_cleanup_incomplete', ['customer_id' => $customer->id]);
            }
        });
    }
}
