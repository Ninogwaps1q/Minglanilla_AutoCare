<?php

namespace App\Services;

use App\Jobs\DeliverWorkflowAlert;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\RepairJob;
use App\Models\RepairJobStatusHistory;
use App\Models\Shop;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use App\Models\WorkflowAlert;
use App\Notifications\WorkflowEvent;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CustomerWorkflowAlerts
{
    public function appointmentReminders(): void
    {
        $now = now(Appointment::TIMEZONE)->toImmutable();
        $tomorrow = $now->addHours(24);
        Appointment::query()->where('status', 'confirmed')
            ->where('appointment_date', '>=', $now->toDateString())
            ->where('appointment_date', '<', $tomorrow->addDay()->toDateString())
            ->whereDoesntHave('rescheduleRequests', fn ($query) => $query->where('status', 'pending'))
            ->with(['shop', 'vehicle'])->lazyById()->each(function (Appointment $appointment) use ($now, $tomorrow): void {
                $start = $appointment->startsAt();
                if ($start <= $now || $start > $tomorrow) {
                    return;
                }
                $hours = $start <= $now->addHours(2) ? 2 : 24;
                $this->record(WorkflowEvent::AppointmentReminder, "appointment:{$appointment->id}:reminder:{$hours}",
                    $appointment->customer_id, $appointment->shop, $appointment->vehicle, [
                        'appointment_id' => $appointment->id, 'starts_at' => $start->toIso8601String(),
                    ]);
                if ($appointment->shop->isIndependentMechanic() && $appointment->shop->mechanicEntitlements()['reminders']) {
                    DB::transaction(function () use ($appointment, $start): void {
                        $locked = Appointment::query()->where('status', 'confirmed')->lockForUpdate()->find($appointment->id);
                        if (! $locked || ! $locked->startsAt()->equalTo($start) || $locked->rescheduleRequests()->where('status', 'pending')->exists()) {
                            return;
                        }
                        $notifications = $appointment->shop->owner->notifications();
                        $startsAt = $start->toIso8601String();
                        if ($notifications->where('type', 'appointment_reminder')->where('data->appointment_id', $appointment->id)->where('data->starts_at', $startsAt)->exists()) {
                            return;
                        }
                        $notifications->create([
                            'type' => 'appointment_reminder', 'title' => 'Upcoming mobile visit',
                            'message' => 'Check your schedule for the mobile visit on '.$start->format('M j, g:i A').' PHT.',
                            'data' => ['appointment_id' => $appointment->id, 'starts_at' => $startsAt, 'mechanic_update' => true],
                        ]);
                    });
                }
            });
    }

    public function mechanicInvoiceReminders(): void
    {
        $today = today(Appointment::TIMEZONE)->toDateString();
        Invoice::query()->whereIn('status', ['unpaid', 'partial'])->whereDate('due_date', $today)
            ->whereHas('shop', fn ($query) => $query->where('provider_type', 'independent_mechanic'))
            ->lazyById()->each(function (Invoice $invoice) use ($today): void {
                DB::transaction(function () use ($invoice, $today): void {
                    $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
                    if ($invoice->due_date?->toDateString() !== $today || $invoice->remainingBalance() <= 0) {
                        return;
                    }
                    $notifications = $invoice->customer->notifications();
                    if ($notifications->where('type', 'invoice_due')->where('data->invoice_id', $invoice->id)->where('data->due_on', $today)->exists()) {
                        return;
                    }
                    $notifications->create([
                        'type' => 'invoice_due', 'title' => 'Mobile service payment due',
                        'message' => 'Invoice '.$invoice->invoice_number.' is due today.',
                        'data' => ['invoice_id' => $invoice->id, 'due_on' => $today, 'mechanic_update' => true],
                    ]);
                });
            });
    }

    public function created(Model $model): void
    {
        match (true) {
            $model instanceof RepairJobStatusHistory => $this->repairChanged($model),
            $model instanceof VehicleInspection => $this->inspectionSaved($model),
            $model instanceof Invoice => $this->invoiceIssued($model),
            $model instanceof Quotation => $this->estimateSent($model),
            default => null,
        };
    }

    public function updated(Model $model): void
    {
        if ($model instanceof Quotation && $model->wasChanged('status')) {
            if ($model->status === 'sent') {
                $this->estimateSent($model);
            } else {
                $this->afterCommit($model, fn () => WorkflowAlert::query()
                    ->where('event_key', "estimate:{$model->id}:sent")->update(['next_reminder_at' => null]));
            }
        }
        if ($model instanceof Invoice && $model->wasChanged('status') && $model->status === 'paid') {
            $this->afterCommit($model, fn () => $this->pickupReady($model));
        }
        if ($model instanceof Payment) {
            $event = match (true) {
                $model->wasChanged('refund_status') && $model->refund_status === 'succeeded' => WorkflowEvent::PaymentRefunded,
                $model->wasChanged('status') && $model->status === 'confirmed' => WorkflowEvent::PaymentReceived,
                $model->wasChanged('status') && in_array($model->status, ['failed', 'rejected'], true) => WorkflowEvent::PaymentFailed,
                default => null,
            };
            if ($event) {
                $this->afterCommit($model, fn () => $this->paymentChanged($model, $event));
            }
        }
    }

    public function reminder(WorkflowAlert $source): void
    {
        DB::transaction(function () use ($source): void {
            $source = WorkflowAlert::query()->lockForUpdate()->findOrFail($source->id);
            if (! $source->next_reminder_at || $source->next_reminder_at->isFuture()) {
                return;
            }
            $estimate = Quotation::query()->whereKey($source->data['quotation_id'])->where('status', 'sent')
                ->where(fn ($query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', today()))
                ->first();

            $at48 = $source->created_at->copy()->addHours(48);
            $hours = $at48->isFuture() ? 24 : 48;
            if ($estimate) {
                $this->estimate($estimate, WorkflowEvent::EstimateReminder, (string) $hours);
                $shop = $estimate->shop;
                if ($shop?->isIndependentMechanic() && $shop->mechanicEntitlements()['reminders']) {
                    $notifications = $shop->owner->notifications();
                    if (! $notifications->where('type', 'quotation_reminder')->where('data->quotation_id', $estimate->id)->exists()) {
                        $notifications->create([
                            'type' => 'quotation_reminder', 'title' => 'Estimate awaiting customer response',
                            'message' => 'Your estimate '.$estimate->quotation_number.' is still awaiting a decision.',
                            'data' => ['quotation_id' => $estimate->id, 'mechanic_update' => true],
                        ]);
                    }
                }
            }
            $source->update(['next_reminder_at' => $estimate && $hours === 24 ? $at48 : null]);
        });
    }

    /** @param array<string, mixed> $session */
    public function checkoutProblem(Payment $payment, array $session): void
    {
        if (($session['id'] ?? null) !== $payment->paymongo_session_id) {
            return;
        }
        $attributes = $session['attributes'] ?? [];
        $intent = $attributes['payment_intent'] ?? [];
        $intentId = $intent['id'] ?? null;
        $attempt = match (true) {
            ($attributes['status'] ?? null) === 'expired' => 'expired',
            ($intent['attributes']['status'] ?? null) === 'awaiting_payment_method'
                && filled($intent['attributes']['last_payment_error'] ?? null)
                && is_string($intentId) && preg_match('/^pi_[A-Za-z0-9_-]+$/D', $intentId) => hash('sha256', $intentId),
            default => null,
        };
        if ($attempt !== null) {
            $this->afterCommit($payment, function () use ($payment, $attempt): void {
                $payment = $payment->fresh();
                if (in_array($payment?->status, ['checkout_pending', 'cancelled'], true) && ($invoice = $payment->invoice)) {
                    $this->forInvoice($invoice, WorkflowEvent::PaymentFailed, "checkout:{$payment->id}:{$attempt}");
                }
            });
        }
    }

    private function repairChanged(RepairJobStatusHistory $history): void
    {
        if ($history->from_status === $history->to_status) {
            return;
        }

        $event = match ($history->to_status) {
            'diagnosis' => WorkflowEvent::InspectionStarted,
            'approved' => WorkflowEvent::RepairApproved,
            'repairing', 'in_progress' => match ($history->from_status) {
                'waiting_parts' => WorkflowEvent::RepairResumed,
                'in_progress', 'repairing' => null,
                default => WorkflowEvent::RepairStarted,
            },
            'waiting_parts' => WorkflowEvent::RepairDelayed,
            'completed' => WorkflowEvent::RepairCompleted,
            'released' => WorkflowEvent::VehicleReleased,
            default => null,
        };
        if ($event) {
            $this->afterCommit($history, function () use ($history, $event): void {
                $job = $history->repairJob;
                $this->forJob($job, $event, "repair-history:{$history->id}");
                if ($event === WorkflowEvent::RepairCompleted && $job?->invoice?->status === 'paid') {
                    $this->pickupReady($job->invoice);
                }
            });
        }
    }

    private function inspectionSaved(VehicleInspection $inspection): void
    {
        $this->afterCommit($inspection, function () use ($inspection): void {
            $job = $inspection->repairJob;
            if (! $job) {
                return;
            }
            $items = $inspection->items()->orderBy('id')->get(['category', 'item_name', 'condition', 'recommendation']);
            $problems = $items->whereIn('condition', ['Needs Attention', 'Critical']);
            $fingerprint = hash('sha256', json_encode([
                $inspection->inspection_date?->toDateString(), $inspection->overall_condition, $inspection->notes, $items,
            ], JSON_THROW_ON_ERROR));

            $this->forJob($job, WorkflowEvent::InspectionResults, "inspection:{$job->id}:{$fingerprint}", [
                'problems' => $problems->count(),
                'findings' => $problems->pluck('item_name')->take(3)->values()->all(),
                'photos' => $job->photos()->whereIn('stage', ['damage', 'before'])->count(),
            ]);
        });
    }

    private function estimateSent(Quotation $estimate): void
    {
        if ($estimate->status === 'sent') {
            $this->afterCommit($estimate, fn () => $this->estimate($estimate, WorkflowEvent::EstimateReady, 'sent'));
        }
    }

    private function estimate(Quotation $estimate, WorkflowEvent $event, string $suffix): void
    {
        $this->record($event, "estimate:{$estimate->id}:{$suffix}", $estimate->customer_id, $estimate->shop, $estimate->vehicle, [
            'quotation_id' => $estimate->id,
            'revised' => $estimate->is_supplemental,
        ]);
    }

    private function invoiceIssued(Invoice $invoice): void
    {
        $this->afterCommit($invoice, function () use ($invoice): void {
            if ($invoice->status === 'unpaid') {
                $this->forInvoice($invoice, WorkflowEvent::InvoiceReady, "invoice:{$invoice->id}");
            } elseif ($invoice->status === 'paid') {
                $this->pickupReady($invoice);
            }
        });
    }

    private function paymentChanged(Payment $payment, WorkflowEvent $event): void
    {
        if ($invoice = $payment->invoice) {
            $this->forInvoice($invoice, $event, "payment:{$payment->id}:{$event->value}");
        }
    }

    private function pickupReady(Invoice $invoice): void
    {
        $job = $invoice->repairJob;
        if ($job?->status === 'completed') {
            $this->forJob($job, WorkflowEvent::PickupReady, "pickup:{$job->id}");
        }
    }

    private function forJob(?RepairJob $job, WorkflowEvent $event, string $key, array $details = []): void
    {
        if ($job) {
            $this->record($event, $key, $job->customer_id, $job->shop, $job->vehicle, [
                'repair_job_id' => $job->id, ...$details,
            ]);
        }
    }

    private function forInvoice(Invoice $invoice, WorkflowEvent $event, string $key): void
    {
        $this->record($event, $key, $invoice->customer_id, $invoice->shop, $invoice->repairJob?->vehicle, ['invoice_id' => $invoice->id]);
    }

    private function record(WorkflowEvent $event, string $key, int $customerId, ?Shop $shop, ?Vehicle $vehicle, array $details): void
    {
        if (! $shop) {
            return;
        }
        $alert = WorkflowAlert::query()->firstOrCreate(['event_key' => $key], [
            'customer_id' => $customerId,
            'event' => $event,
            'next_reminder_at' => $event === WorkflowEvent::EstimateReady ? now()->addHours(24) : null,
            'data' => [
                'shop' => $shop->name,
                'vehicle' => $vehicle ? trim($vehicle->make.' '.$vehicle->model) : null,
                'plate' => $vehicle?->plate_number,
                ...$details,
            ],
        ]);

        if ($alert->wasRecentlyCreated) {
            $this->afterCommit($alert, fn () => DeliverWorkflowAlert::dispatch($alert->id));
        }
    }

    private function afterCommit(Model $model, callable $callback): void
    {
        $deliver = function () use ($model, $callback): void {
            try {
                $callback();
            } catch (Throwable $exception) {
                Log::error('workflow_alert.record_failed', ['model' => $model::class, 'id' => $model->getKey(), 'exception_type' => $exception::class]);
            }
        };

        /** @var Connection $connection */
        $connection = $model->getConnection();
        if ($connection->transactionLevel() > 0) {
            $connection->afterCommit($deliver);
        } else {
            $deliver();
        }
    }
}
