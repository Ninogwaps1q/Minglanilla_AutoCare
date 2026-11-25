@props(['repairJob'])

<section class="service-journey mb-3" aria-label="Service progress">
    <div class="service-journey-heading">
        <h3>{{ __('ui.' . 'Service progress') }}</h3>
        <span>{{ $repairJob->status === 'cancelled' ? __('ui.Cancelled') : __('ui.From booking to release') }}</span>
    </div>
    @if ($repairJob->status === 'cancelled')
        <p class="mb-0">{{ __('ui.' . 'This repair was cancelled. Contact the shop if you need a new service request.') }}</p>
    @else
        <ol class="service-journey-steps">
            @foreach ($repairJob->serviceStages() as $stage)
                <li class="is-{{ $stage['state'] }}" @if ($stage['state'] === 'current') aria-current="step" @endif>
                    <span class="service-journey-marker" aria-hidden="true"><i class="bi bi-{{ $stage['state'] === 'complete' ? 'check' : 'circle-fill' }}"></i></span>
                    <span>{{ __('ui.'.$stage['label']) }}</span>
                </li>
            @endforeach
        </ol>
    @endif
</section>
