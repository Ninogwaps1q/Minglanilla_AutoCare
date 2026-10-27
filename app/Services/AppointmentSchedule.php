<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ServiceCase;
use App\Models\Shop;
use Illuminate\Validation\ValidationException;

class AppointmentSchedule
{
    /** @return array<int, array{start: int, end: int}> */
    public function bookedIntervals(Shop $shop, string $date, ?int $exceptAppointmentId = null, ?int $exceptServiceCaseId = null): array
    {
        $appointments = $shop->appointments()
            ->whereDate('appointment_date', $date)
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->when($exceptAppointmentId, fn ($query) => $query->whereKeyNot($exceptAppointmentId))
            ->with('service:id,estimated_duration')
            ->get(['id', 'service_id', 'appointment_time', 'duration_minutes'])
            ->map(function (Appointment $appointment): array {
                $start = $this->minutes($appointment->appointment_time->format('H:i'));
                $duration = (int) ($appointment->duration_minutes ?: $appointment->service?->estimated_duration ?: 60);

                return ['start' => $start, 'end' => $start + $duration];
            })->all();

        $followUps = ServiceCase::query()->where('shop_id', $shop->id)
            ->whereNull('appointment_id')
            ->whereDate('scheduled_at', $date)
            ->whereIn('schedule_status', ['pending', 'confirmed'])
            ->when($exceptServiceCaseId, fn ($query) => $query->whereKeyNot($exceptServiceCaseId))
            ->get(['scheduled_at'])
            ->map(function (ServiceCase $case): array {
                $start = $this->minutes($case->scheduled_at->format('H:i'));

                return ['start' => $start, 'end' => $start + 60];
            })->all();

        return [...$appointments, ...$followUps];
    }

    public function fitsHours(Shop $shop, string $time, int $duration): bool
    {
        $start = $this->minutes($time);
        $end = $start + $duration;

        return $duration > 0 && $end <= 1440
            && (! $shop->opening_time || $start >= $this->minutes(substr($shop->opening_time, 0, 5)))
            && (! $shop->closing_time || $end <= $this->minutes(substr($shop->closing_time, 0, 5)));
    }

    public function assertRescheduleAvailable(Shop $shop, Appointment $appointment, string $date, string $time): void
    {
        if ($shop->shop_status !== 'active' || $shop->verification_status !== 'approved') {
            throw ValidationException::withMessages(['requested_time' => 'This shop is not accepting appointment changes.']);
        }
        if ($date.' '.$time <= now()->format('Y-m-d H:i')) {
            throw ValidationException::withMessages(['requested_time' => 'Choose a future appointment time.']);
        }
        $duration = (int) ($appointment->duration_minutes ?: $appointment->service?->estimated_duration ?: 60);
        if (! $this->fitsHours($shop, $time, $duration)) {
            throw ValidationException::withMessages(['requested_time' => 'The service must finish before the shop closes.']);
        }
        if (! $this->hasCapacity($this->bookedIntervals($shop, $date, $appointment->id), $time, $duration, (int) $shop->booking_capacity)) {
            throw ValidationException::withMessages(['requested_time' => 'The shop is fully booked at that time. Choose another slot.']);
        }
    }

    /** @param array<int, array{start: int, end: int}> $intervals */
    public function hasCapacity(array $intervals, string $time, int $duration, int $capacity): bool
    {
        $start = $this->minutes($time);
        $end = $start + $duration;
        $changes = [];

        foreach ($intervals as $interval) {
            if ($interval['start'] >= $end || $interval['end'] <= $start) {
                continue;
            }

            $overlapStart = max($start, $interval['start']);
            $overlapEnd = min($end, $interval['end']);
            $changes[$overlapStart] = ($changes[$overlapStart] ?? 0) + 1;
            $changes[$overlapEnd] = ($changes[$overlapEnd] ?? 0) - 1;
        }

        ksort($changes, SORT_NUMERIC);
        $occupied = 0;
        foreach ($changes as $change) {
            $occupied += $change;
            if ($occupied >= $capacity) {
                return false;
            }
        }

        return true;
    }

    private function minutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $hour * 60 + $minute;
    }
}
