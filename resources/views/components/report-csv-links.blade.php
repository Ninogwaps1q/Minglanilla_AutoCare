@props(['from', 'to', 'routeName', 'datasets'])
<section class="surface p-3 mb-4" aria-label="{{ __('extensions.export.title') }}">
    <h2 class="h6 mb-2">{{ __('extensions.export.title') }}</h2>
    <div class="d-flex gap-2 flex-wrap">
        @foreach ($datasets as $dataset)
            <a class="btn btn-sm btn-outline-primary" href="{{ route($routeName, ['dataset' => $dataset, 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">{{ __('extensions.export.'.$dataset) }} CSV</a>
        @endforeach
    </div>
</section>
