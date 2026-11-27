@props(['repairJob', 'detailed' => false])

@if ($detailed)
    @php
        $stageTimes = [
            'Booking' => $repairJob->appointment?->created_at,
            'Repair opened' => $repairJob->created_at,
            'Inspection' => $repairJob->inspections->sortByDesc('inspection_date')->first()?->inspection_date,
            'Estimate approval' => $repairJob->quotation?->approvalRecords->first()?->decided_at
                ?? $repairJob->statusHistories->firstWhere('to_status', 'waiting_approval')?->changed_at,
            'Repair' => $repairJob->started_at,
            'Payment' => $repairJob->invoice?->payments->where('status', 'confirmed')->sortByDesc('reviewed_at')->first()?->reviewed_at,
            'Vehicle release' => $repairJob->statusHistories->firstWhere('to_status', 'released')?->changed_at,
        ];
    @endphp
@endif

<section @class(['service-journey', 'mb-3', 'service-journey-detailed' => $detailed]) aria-label="{{ __('ui.Service progress') }}">
    <div class="service-journey-heading">
        <h3>{{ __('ui.' . 'Service progress') }}</h3>
        <span>{{ $repairJob->status === 'cancelled' ? __('ui.Cancelled') : ($detailed ? __('repair_display.manila_time') : __('ui.From booking to release')) }}</span>
    </div>
    @if ($repairJob->status === 'cancelled')
        <p class="mb-0">{{ __('ui.' . 'This repair was cancelled. Contact the shop if you need a new service request.') }}</p>
    @else
        <ol class="service-journey-steps">
            @foreach ($repairJob->serviceStages() as $stage)
                <li class="is-{{ $stage['state'] }}" @if ($stage['state'] === 'current') aria-current="step" @endif>
                    <span class="service-journey-marker" aria-hidden="true"><i class="bi bi-{{ $stage['state'] === 'complete' ? 'check' : 'circle-fill' }}"></i></span>
                    <div>
                        <span>{{ __('ui.'.$stage['label']) }}</span>
                        @if ($detailed)
                            @if ($stage['state'] !== 'current')
                                <span class="visually-hidden">{{ __('repair_display.timeline_'.$stage['state']) }}</span>
                            @endif
                            @if ($stage['state'] !== 'upcoming' && ($at = $stageTimes[$stage['label']] ?? null))
                                @if ($stage['label'] === 'Inspection')
                                    <time class="small text-secondary d-block" datetime="{{ $at->toDateString() }}">{{ $at->format('M j, Y') }}</time>
                                @else
                                    @php($manila = $at->copy()->setTimezone(\App\Models\Appointment::TIMEZONE))
                                    <time class="small text-secondary d-block" datetime="{{ $manila->toIso8601String() }}">{{ $manila->format('M j, Y · g:i A') }}</time>
                                @endif
                            @endif
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</section>
