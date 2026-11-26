<x-layouts.app :title="__('alerts.preferences')">
    <div class="page-heading"><h1>{{ __('alerts.preferences') }}</h1></div>
    <x-form-errors />
    <section class="surface p-3 mb-3" aria-labelledby="push-heading" data-push-preferences data-public-key="{{ config('services.web_push.public_key') }}" data-url="{{ route('preferences.push.store') }}" data-success="{{ __('alerts.push_saved') }}" data-disabled="{{ __('alerts.push_disabled') }}" data-error="{{ __('alerts.push_error') }}">
        <h2 class="h5" id="push-heading">{{ __('alerts.push') }}</h2>
        @if ($pushConfigured)
            <p>{{ __('workflow.push_help') }}</p>
            <button class="btn btn-outline-primary" type="button" data-push-enable hidden>{{ __('alerts.push_allow') }}</button>
        @else
            <p class="mb-0">{{ __('alerts.push_setup') }}</p>
        @endif
        <button class="btn btn-outline-secondary" type="button" data-push-disable @if (! $user->hasConsentFor('push')) hidden @endif>{{ __('alerts.push_revoke') }}</button>
        <p class="mb-0" role="status" data-push-status></p>
    </section>
    <form class="surface p-3 p-lg-4 mb-4 alert-preferences" method="POST" action="{{ route('preferences.notifications.update') }}">@csrf @method('PUT')
        @php($restorePreferences = $errors->any())
        <h2 class="h5">{{ __('alerts.sms') }}</h2>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" value="1" id="sms-consent" name="sms_consent" @checked($restorePreferences ? old('sms_consent') : $user->hasConsentFor('sms'))>
            <label class="form-check-label" for="sms-consent">{{ __('alerts.sms_consent') }}</label>
        </div>
        <div class="mb-3">
            <label class="form-label" for="sms-phone">{{ __('alerts.phone') }}</label>
            <input class="form-control @error('sms_phone') is-invalid @enderror" type="tel" id="sms-phone" name="sms_phone" value="{{ old('sms_phone', $user->phone) }}" autocomplete="tel" maxlength="30" aria-describedby="sms-phone-help">
            <p class="form-text" id="sms-phone-help">{{ __('alerts.phone_format') }}</p>
            @error('sms_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        @foreach ([true, false] as $isServiceStage)
        @if ($isServiceStage)
            <section class="mb-3" aria-labelledby="notification-stages">
                <h2 class="h5 mb-3" id="notification-stages">{{ __('workflow.preferences_title') }}</h2>
        @else
            <details class="workspace-disclosure" @if($restorePreferences) open @endif>
                <summary class="fw-semibold">{{ __('workflow.other_title') }}</summary>
        @endif
            <div class="row g-3">
            @foreach ($events as $event)
                @continue($event->supportsSms() !== $isServiceStage)
                <fieldset class="col-12 col-xl-6">
                    <legend class="h6">{{ $event->value === 'messages' ? __('extensions.chat.preferences') : (in_array($event->value, ['inspection', 'release', 'payment'], true) ? __('workflow.event_'.$event->value) : __('alerts.event_'.$event->value)) }}</legend>
                    @foreach ($event->supportsSms() ? ['email', 'sms', 'push'] : ['email', 'push'] as $channel)
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" value="1" id="{{ $channel }}-{{ $event->value }}" name="{{ $channel }}[{{ $event->value }}]" @checked($restorePreferences ? old($channel.'.'.$event->value) : ($user->communication_preferences[$channel][$event->value] ?? ($event->value !== 'messages' || $channel === 'push')))>
                            <label for="{{ $channel }}-{{ $event->value }}" class="form-check-label">{{ __('alerts.'.$channel) }}</label>
                        </div>
                    @endforeach
                    @if ($event->value === 'messages')
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" value="1" id="message-preview" name="message_preview" @checked($restorePreferences ? old('message_preview') : ($user->communication_preferences['message_preview'] ?? false))>
                            <label for="message-preview" class="form-check-label">{{ __('extensions.chat.preview') }}</label>
                        </div>
                    @endif
                </fieldset>
            @endforeach
            </div>
        @if ($isServiceStage)
            </section>
        @else
            </details>
        @endif
        @endforeach
        <button class="btn btn-primary mt-3">{{ __('alerts.save') }}</button>
    </form>
</x-layouts.app>
