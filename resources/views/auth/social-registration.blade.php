<x-layouts.public :title="__('privacy.finish_registration')">
    <section class="auth-page container py-5"><div class="auth-panel mx-auto">
        <h1>{{ __('privacy.finish_registration') }}</h1>
        <x-form-errors />
        <form method="POST" action="{{ route('social.registration.store') }}" class="vstack gap-3">@csrf
            <x-legal-consent />
            <button class="btn btn-primary">{{ __('privacy.create_customer') }}</button>
        </form>
    </div></section>
</x-layouts.public>
