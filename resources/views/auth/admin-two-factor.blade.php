<x-layouts.public title="Verify admin sign-in">
    <section class="auth-page container py-5"><div class="auth-panel mx-auto">
    <h1 class="h3 mb-2">Verify admin sign-in</h1>
    <p class="text-secondary">Enter the six-digit code sent to your account email. It expires in 10 minutes.</p>
    <x-form-errors />
    <form method="POST" action="{{ route('admin.two-factor.store') }}">
        @csrf
        <label class="form-label" for="admin-code">Email code</label>
        <input class="form-control mb-3" id="admin-code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus>
        <button class="btn btn-primary" type="submit">Verify and continue</button>
    </form>
    <form method="POST" action="{{ route('admin.two-factor.resend') }}" class="mt-3">@csrf<button class="btn btn-outline-primary" type="submit">Send a new code</button></form>
    </div></section>
</x-layouts.public>
