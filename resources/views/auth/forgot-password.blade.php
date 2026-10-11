<x-layouts.public title="Reset password">
    <section class="auth-page container py-5">
        <div class="auth-panel mx-auto">
            <h1>Reset password</h1>
            <p class="text-secondary mb-4">Enter your account email to receive a reset link.</p><x-form-errors />
            <form method="POST" action="{{ route('password.email') }}" class="vstack gap-3">@csrf <div><label
                        class="form-label" for="email">Email address</label><input id="email" class="form-control"
                        type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
                </div><button class="btn btn-primary w-100">Email reset link</button></form>
            <p class="small mb-0 mt-4"><a href="{{ route('login') }}">Return to sign in</a></p>
        </div>
    </section>
</x-layouts.public>