<x-layouts.public title="Verify email">
    <section class="auth-page container py-5">
        <div class="auth-panel mx-auto"><i class="bi bi-envelope-check feature-icon" aria-hidden="true"></i>
            <h1>Verify your email</h1>
            <p class="text-secondary mb-4">A verification link is sent when email delivery is configured. In local
                development, check the application log mailer.</p><a class="btn btn-primary w-100"
                href="{{ route('dashboard') }}">Continue to dashboard</a>
        </div>
    </section>
</x-layouts.public>