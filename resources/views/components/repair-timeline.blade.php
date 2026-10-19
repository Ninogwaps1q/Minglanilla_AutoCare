@props(['repairJob'])

@php $workflowEvents = $repairJob->workflowEvents(); @endphp

<details class="repair-details mb-3">
    <summary>Workflow history · {{ $workflowEvents->count() }} {{ $workflowEvents->count() === 1 ? 'event' : 'events' }}</summary>
    @if ($workflowEvents->isEmpty())
        <p class="small text-secondary mt-3 mb-0">No recorded changes yet. Older stages may not have a complete history.</p>
    @else
        <ol class="list-group list-group-flush mt-3" aria-label="Repair workflow history">
            @foreach ($workflowEvents as $event)
                <li class="list-group-item px-0 d-flex flex-wrap justify-content-between gap-2">
                    <span><strong>{{ $event['label'] }}</strong><span class="text-secondary small d-block">{{ $event['actor'] }}</span></span>
                    <time class="small text-secondary" datetime="{{ $event['at']->toIso8601String() }}">{{ $event['at']->format('M j, Y · g:i A') }}</time>
                </li>
            @endforeach
        </ol>
    @endif
</details>
