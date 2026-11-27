<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ServiceCase;
use App\Models\Shop;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AppointmentSchedule
{
    public function duration(Shop $shop, int $serviceMinutes): int
    {
        return $serviceMinutes + ($shop->isIndependentMechanic() ? (int) ($shop->mechanic_settings['buffer_minutes'] ?? 0) : 0);
    }

    public function isUpcoming(string $date, string $time): bool
    {
        return $date.' '.$time > now(Appointment::TIMEZONE)->format('Y-m-d H:i');
    }

    public function mechanicHasActiveVisit(Shop $shop, Appointment $appointment): bool
    {
        return $shop->appointments()->whereKeyNot($appointment->id)
            ->where(fn ($query) => $query->where('status', 'in_progress')
                ->orWhere(fn ($travelling) => $travelling->where('status', 'confirmed')->whereNotNull('en_route_at')))
            ->exists();
    }

    /** @return array<int, array{start: int, end: int}> */
    public function bookedIntervals(Shop $shop, string $date, ?int $exceptAppointmentId = null, ?int $exceptServiceCaseId = null): array
    {
        $appointments = $shop->appointments()
            ->whereDate('appointment_date', $date)
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->when($exceptAppointmentId, fn ($query) => $query->whereKeyNot($exceptAppointmentId))
            ->with('service:id,estimated_duration')
            ->get(['id', 'service_id', 'appointment_time', 'duration_minutes'])
            ->map(function (Appointment $appointment) use ($shop): array {
                $start = $this->minutes($appointment->appointment_time->format('H:i'));
                $duration = (int) ($appointment->duration_minutes ?: $appointment->service?->estimated_duration ?: 60);

                return ['start' => $start, 'end' => $start + $this->duration($shop, $duration)];
            })->all();

        $followUps = ServiceCase::query()->where('shop_id', $shop->id)
            ->whereNull('appointment_id')
            ->whereDate('scheduled_at', $date)
            ->whereIn('schedule_status', ['pending', 'confirmed'])
            ->when($exceptServiceCaseId, fn ($query) => $query->whereKeyNot($exceptServiceCaseId))
            ->get(['scheduled_at'])
            ->map(function (ServiceCase $case) use ($shop): array {
                $start = $this->minutes($case->scheduled_at->format('H:i'));

                return ['start' => $start, 'end' => $start + $this->duration($shop, 60)];
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

    public function isAvailable(Shop $shop, string $date, string $time, int $duration): bool
    {
        if (! $this->fitsHours($shop, $time, $duration)) {
            return false;
        }
        if ($shop->operating_days !== null && ! in_array(Carbon::parse($date)->dayOfWeekIso, array_map('intval', $shop->operating_days), true)) {
            return false;
        }
        $start = $this->minutes($time);
        $end = $start + $duration;
        foreach ($this->blocksFor($shop, $date) as $block) {
            if (($block['date'] ?? null) === $date && $start < $this->minutes($block['end']) && $end > $this->minutes($block['start'])) {
                return false;
            }
        }

        return true;
    }

    public function blocksFor(Shop $shop, string $date): array
    {
        $blocks = collect($shop->schedule_blocks ?? [])->where('date', $date)->values()->all();
        $rest = $shop->isIndependentMechanic() ? ($shop->mechanic_settings['rest'] ?? null) : null;

        return $rest ? [...$blocks, ['date' => $date, ...$rest]] : $blocks;
    }

    /** @return array{open: bool, time: string|null}|null */
    public function directoryHours(Shop $shop, Carbon $now): ?array
    {
        if (! $shop->opening_time || ! $shop->closing_time) {
            return null;
        }

        $date = $now->toDateString();
        $time = $now->format('H:i');
        $opening = substr($shop->opening_time, 0, 5);
        $closing = substr($shop->closing_time, 0, 5);
        $blocks = collect($this->blocksFor($shop, $date));

        if ($this->isAvailable($shop, $date, $time, 1)) {
            $until = $blocks->pluck('start')->map(fn (string $start): string => substr($start, 0, 5))
                ->filter(fn (string $start): bool => $start > $time && $start < $closing)->min() ?? $closing;

            return ['open' => true, 'time' => Carbon::parse($until)->format('g:i A')];
        }

        $next = $blocks->pluck('end')->push($opening)->map(fn (string $end): string => substr($end, 0, 5))
            ->filter(fn (string $end): bool => $end > $time && $this->isAvailable($shop, $date, $end, 1))->min();

        return ['open' => false, 'time' => $next ? Carbon::parse($next)->format('g:i A') : null];
    }

    public function assertRescheduleAvailable(Shop $shop, Appointment $appointment, string $date, string $time): void
    {
        if ($shop->shop_status !== 'active' || $shop->verification_status !== 'approved') {
            throw ValidationException::withMessages(['requested_time' => 'This shop is not accepting appointment changes.']);
        }
        if (! $this->isUpcoming($date, $time)) {
            throw ValidationException::withMessages(['requested_time' => 'Choose a future appointment time.']);
        }
        $duration = $this->duration($shop, (int) ($appointment->duration_minutes ?: $appointment->service?->estimated_duration ?: 60));
        if (! $this->isAvailable($shop, $date, $time, $duration)) {
            throw ValidationException::withMessages(['requested_time' => 'The shop is closed or this time is blocked. Choose another slot.']);
        }
        if (! $this->hasCapacity($this->bookedIntervals($shop, $date, $appointment->id), $time, $duration, $shop->appointmentCapacity())) {
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
