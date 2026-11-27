<?php

namespace App\Notifications;

use App\Models\Notification;

enum ServiceAlert: string
{
    case Booking = 'booking';
    case Messages = 'messages';
    case Inspection = 'inspection';
    case Estimate = 'estimate';
    case Repair = 'repair';
    case Payment = 'payment';
    case Release = 'release';
    case Roadside = 'roadside';
    case Plan = 'plan';
    case Maintenance = 'maintenance';

    public function supportsSms(): bool
    {
        return in_array($this, [self::Booking, self::Messages, self::Inspection, self::Estimate, self::Repair, self::Payment, self::Release, self::Roadside, self::Plan, self::Maintenance], true);
    }

    public static function forMechanicNotification(Notification $notification): ?self
    {
        if (! config('services.sms.mechanic_alerts') || ($notification->data['mechanic_update'] ?? false) !== true) {
            return null;
        }

        return match ($notification->type) {
            'appointment_requested', 'appointment_status', 'appointment_reminder' => self::Booking,
            'quotation_reminder' => self::Estimate,
            'emergency_request' => self::Roadside,
            'shop_plan_reminder' => self::Plan,
            'maintenance_due' => self::Maintenance,
            'invoice_due' => self::Payment,
            default => null,
        };
    }

    public static function forNotification(Notification $notification): ?self
    {
        $status = $notification->data['status'] ?? null;

        return match ($notification->type) {
            'appointment_status' => $status === 'confirmed' ? self::Booking : null,
            'appointment_reschedule_decided' => $status === 'approved' ? self::Booking : null,
            'appointment_cancelled' => self::Booking,
            'message_received' => self::Messages,
            'quotation_sent' => self::Estimate,
            'repair_status' => $status === 'completed' ? self::Repair : null,
            'payment_verification' => in_array($status, ['failed', 'rejected'], true) ? self::Payment : null,
            'emergency_request', 'emergency_status' => self::Roadside,
            default => null,
        };
    }

    public static function forEmailType(string $type): self
    {
        return match (true) {
            str_starts_with($type, 'appointment_') => self::Booking,
            $type === 'message_received' => self::Messages,
            str_starts_with($type, 'quotation_') => self::Estimate,
            str_starts_with($type, 'repair_') => self::Repair,
            str_starts_with($type, 'emergency_') => self::Roadside,
            str_starts_with($type, 'payment_') || $type === 'invoice_due' => self::Payment,
            $type === 'maintenance_due' => self::Maintenance,
            default => self::Plan,
        };
    }
}
