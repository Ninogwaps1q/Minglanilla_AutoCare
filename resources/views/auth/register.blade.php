<x-layouts.public title="Create account">
    <section class="auth-page container py-5">
        <div class="auth-panel mx-auto">
            <h1>Create your account</h1><p class="text-secondary mb-4">Start as a customer or register as a shop owner.</p>
            <x-form-errors />
            <form method="POST" action="{{ route('register') }}" class="vstack gap-3">@csrf
                <div><label class="form-label" for="name">Full name</label><input id="name" class="form-control" name="name" value="{{ old('name') }}" autocomplete="name" required autofocus></div>
                <div><label class="form-label" for="email">Email address</label><input id="email" class="form-control" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required></div>
                <div><label class="form-label" for="role">I am registering as</label><select id="role" class="form-select" name="role"><option value="customer" @selected(old('role') === 'customer')>Customer</option><option value="shop_owner" @selected(old('role') === 'shop_owner')>Shop owner</option></select></div>
                <div><label class="form-label" for="password">Password</label><input id="password" class="form-control" name="password" type="password" autocomplete="new-password" required></div>
                <div><label class="form-label" for="password_confirmation">Confirm password</label><input id="password_confirmation" class="form-control" name="password_confirmation" type="password" autocomplete="new-password" required></div>
                <button class="btn btn-primary w-100">Create account</button>
            </form>
            <p class="small text-secondary mb-0 mt-4">Already registered? <a href="{{ route('login') }}">Sign in</a>.</p>
        </div>
    </section>
</x-layouts.public>
