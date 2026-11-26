@props(['mode' => 'login'])

@php($providers = collect(['google' => 'Google', 'facebook' => 'Facebook'])->filter(fn ($name, $provider) => config("services.{$provider}.client_id") && config("services.{$provider}.client_secret")))

<div class="auth-social">
    @if ($providers->isNotEmpty())
        <div class="auth-social-divider"><span>Or continue with</span></div>
    @endif
    <div class="auth-social-actions">
        @foreach ($providers as $provider => $name)
            <a class="auth-social-button" href="{{ route('social.redirect', $provider) }}">
                <i class="bi bi-{{ $provider }}" aria-hidden="true"></i><span>{{ $name }}</span>
            </a>
        @endforeach
        @unless ($providers->has('facebook'))
            <button type="button" class="auth-social-button text-secondary" aria-describedby="facebook-sign-in-status" disabled>
                <i class="bi bi-facebook" aria-hidden="true"></i><span>Facebook</span>
            </button>
        @endunless
    </div>
    @unless ($providers->has('facebook'))
        <p class="auth-social-note" id="facebook-sign-in-status">{{ __('account_ui.facebook_unavailable') }}</p>
    @endunless
    @if ($providers->isNotEmpty())
        <p class="auth-social-note">
            @if ($mode === 'register')
                Social sign-up creates a customer account. Shop owners should register with email and password.
            @else
                New social sign-ins create customer accounts. Existing accounts can connect a provider during password sign-in.
            @endif
        </p>
    @endif
</div>
