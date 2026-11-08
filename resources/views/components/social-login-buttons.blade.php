@props(['mode' => 'login'])

<div class="auth-social">
    <div class="auth-social-divider"><span>Or continue with</span></div>
    <div class="auth-social-actions">
        <a class="auth-social-button" href="{{ route('social.redirect', 'google') }}">
            <i class="bi bi-google" aria-hidden="true"></i><span>Google</span>
        </a>
        <a class="auth-social-button" href="{{ route('social.redirect', 'facebook') }}">
            <i class="bi bi-facebook" aria-hidden="true"></i><span>Facebook</span>
        </a>
    </div>
    <p class="auth-social-note">
        @if ($mode === 'register')
            Social sign-up creates a customer account. Shop owners should register with email and password.
        @else
            New social sign-ins create customer accounts. Existing accounts can connect a provider during password sign-in.
        @endif
    </p>
</div>
