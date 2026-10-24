@props(['items', 'emptyRoute', 'emptyLabel'])

@php($outstanding = collect($items)->filter(fn (array $item): bool => $item['count'] > 0))

<section class="action-queue mb-4" aria-labelledby="action-queue-title">
    <div class="action-queue-heading">
        <div>
            <h2 id="action-queue-title">Needs your action</h2>
            <p>Open a task to see what to do next.</p>
        </div>
        <span>{{ $outstanding->sum('count') }} open</span>
    </div>
    @if ($outstanding->isEmpty())
        <div class="action-queue-empty"><i class="bi bi-check-circle" aria-hidden="true"></i><span>You are all caught up.</span><a href="{{ $emptyRoute }}">{{ $emptyLabel }}</a></div>
    @else
        <div class="action-queue-list">
            @foreach ($outstanding as $item)
                <a href="{{ $item['href'] }}">
                    <strong>{{ $item['count'] }}</strong>
                    <span><b>{{ $item['label'] }}</b><small>{{ $item['detail'] }}</small></span>
                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </a>
            @endforeach
        </div>
    @endif
</section>
