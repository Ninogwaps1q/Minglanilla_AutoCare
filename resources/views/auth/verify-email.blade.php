<x-layouts.public title="Verify email">
    <section class="auth-page container py-5">
        <div class="auth-panel mx-auto">
            <i class="bi bi-envelope-check feature-icon" aria-hidden="true"></i>
            <h1>Verify your email</h1>
            <p class="text-secondary">We sent a verification link to <strong>{{ auth()->user()->email }}</strong>. Open the link to finish setting up your account.</p>
            <p class="text-secondary small">Check your spam folder if you do not see the email. The link may expire, but you can request a new one below.</p>
            @if (app()->isLocal() && config('mail.default') === 'log')
                <p class="small">Local development: the email is written to the application log.</p>
            @endif
            <form method="POST" action="{{ route('verification.send') }}" class="mb-3">
                @csrf
                <button type="submit" class="btn btn-primary w-100">Resend verification email</button>
            </form>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-outline-secondary w-100">Sign out</button>
            </form>
        </div>
    </section>
</x-layouts.public>
