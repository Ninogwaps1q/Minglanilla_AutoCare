<x-layouts.public title="Sign in">
    <section class="auth-page container py-5">
        <div class="auth-panel mx-auto">
            <h1>Welcome back</h1>
            <p class="text-secondary mb-4">Sign in to manage your vehicle or service shop.</p>
            <x-form-errors />
            <form method="POST" action="{{ route('login') }}" class="vstack gap-3">@csrf
                <div><label class="form-label" for="email">Email address</label><input id="email" class="form-control"
                        name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
                </div>
                <div><label class="form-label" for="password">Password</label><input id="password" class="form-control"
                        name="password" type="password" autocomplete="current-password" required></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="remember" id="remember"
                        value="1" @checked(old('remember'))><label class="form-check-label" for="remember">Remember
                        me</label></div>
                <button class="btn btn-primary w-100">Sign in</button>
            </form>
            <div class="d-flex justify-content-between gap-3 mt-4 small"><a
                    href="{{ route('password.request') }}">Forgot password?</a><a href="{{ route('register') }}">Create
                    account</a></div>
        </div>
    </section>
</x-layouts.public>