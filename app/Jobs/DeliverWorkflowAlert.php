<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Models\Quotation;
use App\Models\WorkflowAlert;
use App\Notifications\Workflow\CustomerWorkflowNotification;
use App\Notifications\WorkflowEvent;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class DeliverWorkflowAlert implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 300;

    public function __construct(public int $alertId) {}

    public function uniqueId(): string
    {
        return (string) $this->alertId;
    }

    public function handle(): void
    {
        $alert = WorkflowAlert::query()->with('customer')->find($this->alertId);
        if (! $alert || $alert->completed_at) {
            return;
        }

        $customer = $alert->customer;
        if ($customer?->status === 'active' && $customer->role === 'customer' && $this->isStillRelevant($alert)) {
            $class = $alert->event->notificationClass();
            /** @var CustomerWorkflowNotification $notification */
            $notification = (new $class($alert))->locale($customer->locale ?? 'en');
            foreach ($notification->via($customer) as $channel) {
                $name = class_basename($channel);
                if (! $this->recordChannel($name, 'sending', claim: true)) {
                    continue;
                }

                try {
                    Notification::sendNow($customer, $notification, [$channel]);
                    $status = 'processed';
                } catch (Throwable $exception) {
                    Log::warning('workflow_alert.channel_failed', ['alert_id' => $this->alertId, 'channel' => $name, 'exception_type' => $exception::class]);
                    $status = 'failed';
                }
                $this->recordChannel($name, $status);
            }
        }

        $alert->update(['completed_at' => now()]);
    }

    private function isStillRelevant(WorkflowAlert $alert): bool
    {
        if ($alert->event === WorkflowEvent::AppointmentReminder) {
            $appointment = Appointment::query()->whereKey($alert->data['appointment_id'])
                ->where('status', 'confirmed')
                ->whereDoesntHave('rescheduleRequests', fn ($query) => $query->where('status', 'pending'))->first();

            return $appointment && $appointment->startsAt()->greaterThan(now())
                && $appointment->startsAt()->toIso8601String() === $alert->data['starts_at'];
        }
        if ($alert->event !== WorkflowEvent::EstimateReminder) {
            return true;
        }

        return Quotation::query()->whereKey($alert->data['quotation_id'])
            ->where('status', 'sent')
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', today()))
            ->exists();
    }

    private function recordChannel(string $channel, string $status, bool $claim = false): bool
    {
        return DB::transaction(function () use ($channel, $status, $claim): bool {
            $alert = WorkflowAlert::query()->lockForUpdate()->findOrFail($this->alertId);
            $channels = $alert->channels ?? [];
            if ($claim && ($alert->completed_at || isset($channels[$channel]))) {
                return false;
            }
            $channels[$channel] = $status;
            $alert->update(['channels' => $channels]);

            return true;
        });
    }
}
