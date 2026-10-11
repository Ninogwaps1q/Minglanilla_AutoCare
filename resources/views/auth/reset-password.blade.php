<x-layouts.public title="Choose new password">
    <section class="auth-page container py-5">
        <div class="auth-panel mx-auto">
            <h1>Choose a new password</h1>
            <p class="text-secondary mb-4">Set a strong password you have not used before.</p><x-form-errors />
            <form method="POST" action="{{ route('password.store') }}" class="vstack gap-3">@csrf <input type="hidden"
                    name="token" value="{{ $request->route('token') }}">
                <div><label class="form-label" for="email">Email address</label><input id="email" class="form-control"
                        type="email" name="email" value="{{ old('email', $request->email) }}" autocomplete="email"
                        required></div>
                <div><label class="form-label" for="password">New password</label><input id="password"
                        class="form-control" type="password" name="password" autocomplete="new-password" required></div>
                <div><label class="form-label" for="password_confirmation">Confirm new password</label><input
                        id="password_confirmation" class="form-control" type="password" name="password_confirmation"
                        autocomplete="new-password" required></div><button class="btn btn-primary w-100">Save
                    password</button>
            </form>
        </div>
    </section>
</x-layouts.public>