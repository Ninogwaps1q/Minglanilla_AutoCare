<?php

namespace App\Notifications;

use App\Models\Notification;

enum ServiceAlert: string
{
    case Booking = 'booking';
    case Inspection = 'inspection';
    case Estimate = 'estimate';
    case Repair = 'repair';
    case Payment = 'payment';
    case Release = 'release';
    case Roadside = 'roadside';
    case Plan = 'plan';

    public function supportsSms(): bool
    {
        return in_array($this, [self::Inspection, self::Estimate, self::Repair, self::Payment, self::Release], true);
    }

    public static function forNotification(Notification $notification): ?self
    {
        $status = $notification->data['status'] ?? null;

        return match ($notification->type) {
            'appointment_status' => $status === 'confirmed' ? self::Booking : null,
            'appointment_reschedule_decided' => $status === 'approved' ? self::Booking : null,
            'appointment_cancelled' => self::Booking,
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
            str_starts_with($type, 'quotation_') => self::Estimate,
            str_starts_with($type, 'repair_') => self::Repair,
            str_starts_with($type, 'emergency_') => self::Roadside,
            str_starts_with($type, 'payment_') => self::Payment,
            default => self::Plan,
        };
    }
}
