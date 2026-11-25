<x-layouts.public :title="__('privacy.'.$document.'_title')">
    <article class="container py-4 py-lg-5"><div class="surface mx-auto p-4 p-lg-5 col-xl-8">
        <h1>{{ __('privacy.'.$document.'_title') }}</h1>
        <p class="alert alert-warning">{{ __('privacy.draft') }} <span class="d-block">{{ __('privacy.version', ['version' => config('privacy.version')]) }}</span></p>
        @foreach (trans('privacy.'.$document.'_sections') as [$heading, $body])
            <section class="mb-4"><h2 class="h5">{{ $heading }}</h2><p class="mb-0">{{ $body }}</p></section>
        @endforeach
        <h2 class="h5">{{ __('privacy.contact_heading') }}</h2>
        @if (config('privacy.contact'))
            <a href="mailto:{{ config('privacy.contact') }}">{{ config('privacy.contact') }}</a>
        @else
            <p>{{ __('privacy.contact_pending') }}</p>
        @endif
    </div></article>
</x-layouts.public>
