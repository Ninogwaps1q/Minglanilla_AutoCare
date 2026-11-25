<x-layouts.app :title="__('alerts.preferences')">
    <div class="page-heading"><h1>{{ __('alerts.preferences') }}</h1></div>
    <x-form-errors />
    <form class="surface p-4 mb-4" method="POST" action="{{ route('preferences.notifications.update') }}">@csrf @method('PUT')
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" value="1" id="sms-consent" name="sms_consent" @checked(old('sms_consent', $user->hasConsentFor('sms')))>
            <label class="form-check-label" for="sms-consent">{{ __('alerts.sms_consent') }}</label>
        </div>
        <div class="mb-3">
            <label class="form-label" for="sms-phone">{{ __('alerts.phone') }}</label>
            <input class="form-control @error('sms_phone') is-invalid @enderror" type="tel" id="sms-phone" name="sms_phone" value="{{ old('sms_phone', $user->phone) }}" autocomplete="tel" maxlength="30">
            @error('sms_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="row g-3">
            @foreach ($events as $event)
                <fieldset class="col-sm-6 col-lg-4">
                    <legend class="h6">{{ __('alerts.event_'.$event->value) }}</legend>
                    @foreach ($event === \App\Notifications\ServiceAlert::Plan ? ['email'] : ['email', 'sms', 'push'] as $channel)
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" value="1" id="{{ $channel }}-{{ $event->value }}" name="{{ $channel }}[{{ $event->value }}]" @checked($user->communication_preferences[$channel][$event->value] ?? true)>
                            <label for="{{ $channel }}-{{ $event->value }}" class="form-check-label">{{ __('alerts.'.$channel) }}</label>
                        </div>
                    @endforeach
                </fieldset>
            @endforeach
        </div>
        <button class="btn btn-primary mt-3">{{ __('alerts.save') }}</button>
    </form>
    <section class="surface p-4" aria-labelledby="push-heading" data-push-preferences data-public-key="{{ config('services.web_push.public_key') }}" data-url="{{ route('preferences.push.store') }}" data-success="{{ __('alerts.push_saved') }}" data-disabled="{{ __('alerts.push_disabled') }}" data-error="{{ __('alerts.push_error') }}">
        <h2 class="h5" id="push-heading">{{ __('alerts.push') }}</h2>
        <p>{{ __('alerts.push_help') }}</p>
        @if (config('services.web_push.public_key'))
            <button class="btn btn-outline-primary" type="button" data-push-enable hidden>{{ __('alerts.push_allow') }}</button>
        @else
            <p>{{ __('alerts.push_setup') }}</p>
        @endif
        <button class="btn btn-outline-secondary" type="button" data-push-disable @if (! $user->hasConsentFor('push')) hidden @endif>{{ __('alerts.push_revoke') }}</button>
        <p class="mt-3 mb-0" role="status" data-push-status></p>
    </section>
</x-layouts.app>
