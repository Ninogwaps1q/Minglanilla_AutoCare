<x-layouts.public title="Confirm your password">
    <section class="auth-page container py-5"><div class="auth-panel mx-auto">
        <h1 class="h3 mb-2">Confirm your password</h1>
        <p class="text-secondary">For refunds and shop transfers, enter your password again before continuing.</p>
        <x-form-errors />
        <form method="POST" action="{{ route('password.confirm.store') }}">@csrf
            <label class="form-label" for="confirm-password">Password</label>
            <input class="form-control mb-3" id="confirm-password" name="password" type="password" autocomplete="current-password" required autofocus>
            <button class="btn btn-primary" type="submit">Confirm and continue</button>
        </form>
    </div></section>
</x-layouts.public>
