<?php

namespace App\Notifications;

use App\Notifications\Workflow\AppointmentReminder;
use App\Notifications\Workflow\EstimateReady;
use App\Notifications\Workflow\EstimateReminder;
use App\Notifications\Workflow\InspectionResults;
use App\Notifications\Workflow\InspectionStarted;
use App\Notifications\Workflow\InvoiceReady;
use App\Notifications\Workflow\PaymentFailed;
use App\Notifications\Workflow\PaymentReceived;
use App\Notifications\Workflow\PaymentRefunded;
use App\Notifications\Workflow\PickupReady;
use App\Notifications\Workflow\RepairApproved;
use App\Notifications\Workflow\RepairCompleted;
use App\Notifications\Workflow\RepairDelayed;
use App\Notifications\Workflow\RepairResumed;
use App\Notifications\Workflow\RepairStarted;
use App\Notifications\Workflow\VehicleReleased;

enum WorkflowEvent: string
{
    case AppointmentReminder = 'appointment_reminder';
    case InspectionStarted = 'inspection_started';
    case InspectionResults = 'inspection_results';
    case EstimateReady = 'estimate_ready';
    case EstimateReminder = 'estimate_reminder';
    case RepairApproved = 'repair_approved';
    case RepairStarted = 'repair_started';
    case RepairResumed = 'repair_resumed';
    case RepairDelayed = 'repair_delayed';
    case RepairCompleted = 'repair_completed';
    case InvoiceReady = 'invoice_ready';
    case PaymentReceived = 'payment_received';
    case PaymentFailed = 'payment_failed';
    case PaymentRefunded = 'payment_refunded';
    case PickupReady = 'pickup_ready';
    case VehicleReleased = 'vehicle_released';

    public function stage(): ServiceAlert
    {
        return match ($this) {
            self::AppointmentReminder => ServiceAlert::Booking,
            self::InspectionStarted, self::InspectionResults => ServiceAlert::Inspection,
            self::EstimateReady, self::EstimateReminder => ServiceAlert::Estimate,
            self::RepairApproved, self::RepairStarted, self::RepairResumed, self::RepairDelayed, self::RepairCompleted => ServiceAlert::Repair,
            self::InvoiceReady, self::PaymentReceived, self::PaymentFailed, self::PaymentRefunded => ServiceAlert::Payment,
            self::PickupReady, self::VehicleReleased => ServiceAlert::Release,
        };
    }

    public function notificationClass(): string
    {
        return match ($this) {
            self::AppointmentReminder => AppointmentReminder::class,
            self::InspectionStarted => InspectionStarted::class,
            self::InspectionResults => InspectionResults::class,
            self::EstimateReady => EstimateReady::class,
            self::EstimateReminder => EstimateReminder::class,
            self::RepairApproved => RepairApproved::class,
            self::RepairStarted => RepairStarted::class,
            self::RepairResumed => RepairResumed::class,
            self::RepairDelayed => RepairDelayed::class,
            self::RepairCompleted => RepairCompleted::class,
            self::InvoiceReady => InvoiceReady::class,
            self::PaymentReceived => PaymentReceived::class,
            self::PaymentFailed => PaymentFailed::class,
            self::PaymentRefunded => PaymentRefunded::class,
            self::PickupReady => PickupReady::class,
            self::VehicleReleased => VehicleReleased::class,
        };
    }

    public function inboxType(): string
    {
        return match ($this) {
            self::EstimateReady => 'quotation_sent',
            self::PaymentReceived, self::PaymentFailed => 'payment_verification',
            self::PaymentRefunded => 'payment_refund',
            default => $this->value,
        };
    }
}
