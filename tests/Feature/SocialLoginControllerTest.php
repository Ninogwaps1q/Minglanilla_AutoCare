<?php

use App\Models\SocialAccount;
use App\Models\User;
use App\Notifications\ImportantEventMail;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('app.url', 'http://localhost:8000');
    config()->set('services.google.redirect', 'http://localhost:8000/auth/google/callback');
    config()->set('services.facebook.redirect', 'http://localhost:8000/auth/facebook/callback');
});

it('shows Google and Facebook sign-in on the login and registration pages', function () {
    config()->set([
        'services.google.client_id' => 'test-google', 'services.google.client_secret' => 'test-google-secret',
        'services.facebook.client_id' => 'test-facebook', 'services.facebook.client_secret' => 'test-facebook-secret',
    ]);
    $this->get(route('login'))->assertSee(route('social.redirect', 'google'))
        ->assertSee(route('social.redirect', 'facebook'))->assertDontSee('facebook-sign-in-status');
    $this->get(route('register'))->assertSee(route('social.redirect', 'google'))
        ->assertSee(route('social.redirect', 'facebook'))->assertDontSee('facebook-sign-in-status');
});

it('shows an unavailable Facebook option when credentials are incomplete and keeps email sign-in', function () {
    config()->set([
        'services.google.client_id' => null, 'services.google.client_secret' => null,
        'services.facebook.client_id' => 'test-facebook', 'services.facebook.client_secret' => null,
    ]);

    foreach (['login', 'register'] as $page) {
        $this->get(route($page))->assertOk()
            ->assertDontSee(route('social.redirect', 'google'))
            ->assertDontSee(route('social.redirect', 'facebook'))
            ->assertSee('aria-describedby="facebook-sign-in-status" disabled', false)
            ->assertSee('Facebook sign-in is unavailable until setup is complete.')
            ->assertSee('Email address');
    }
});

it('redirects to a configured provider', function (string $provider) {
    config()->set("services.{$provider}.client_id", 'test-client');
    config()->set("services.{$provider}.client_secret", 'test-secret');
    Socialite::fake($provider);

    $this->get(config('app.url').route('social.redirect', $provider, false))
        ->assertRedirect("https://socialite.fake/{$provider}/authorize");
})->with(['google', 'facebook']);

it('includes a session-backed state token in the Google authorization request', function () {
    config()->set('services.google.client_id', 'test-client');
    config()->set('services.google.client_secret', 'test-secret');

    $response = $this->get(config('app.url').route('social.redirect', 'google', false));

    $response->assertRedirect()->assertSessionHas('state');
    expect($response->headers->get('Location'))->toContain('state=');
});

it('starts OAuth on the configured loopback host before creating session state', function () {
    config()->set('app.url', 'http://localhost:8000');
    config()->set('services.google.client_id', 'test-client');
    config()->set('services.google.client_secret', 'test-secret');

    $this->get('http://127.0.0.1:8000/auth/google/redirect')
        ->assertRedirect('http://localhost:8000/auth/google/redirect')
        ->assertSessionMissing('state');

    $this->get('http://localhost:8000/auth/google/redirect')
        ->assertRedirect()
        ->assertSessionHas('state');
});

it('uses the configured app URL for the callback even when the request host differs', function () {
    config()->set('app.url', 'https://autocare.example.com');
    config()->set('services.google.redirect', 'https://autocare.example.com/auth/google/callback');
    config()->set('services.google.client_id', 'test-client');
    config()->set('services.google.client_secret', 'test-secret');

    $this->get('http://192.168.1.2:8000/auth/google/redirect')
        ->assertRedirect('https://autocare.example.com/auth/google/redirect')
        ->assertSessionMissing('state');

    $response = $this->get('https://autocare.example.com/auth/google/redirect');
    $response->assertSessionHas('state');
    parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $parameters);

    expect($parameters['redirect_uri'])->toBe('https://autocare.example.com/auth/google/callback');
});

it('starts Google login on localhost when the server computer opens its LAN address', function (string $clientIp) {
    config()->set('services.google.client_id', 'test-client');
    config()->set('services.google.client_secret', 'test-secret');

    $this->withServerVariables(['REMOTE_ADDR' => $clientIp, 'SERVER_ADDR' => '192.168.1.2'])
        ->get('http://192.168.1.2:8000/auth/google/redirect')
        ->assertRedirect('http://localhost:8000/auth/google/redirect')
        ->assertSessionHasNoErrors()
        ->assertSessionMissing('state');

    $response = $this->get('http://localhost:8000/auth/google/redirect');
    $response->assertRedirect()->assertSessionHas('state');
    expect(parse_url($response->headers->get('Location'), PHP_URL_HOST))->toBe('accounts.google.com');
    parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $parameters);
    expect($parameters['redirect_uri'])->toBe('http://localhost:8000/auth/google/callback');
})->with(['192.168.1.2', '127.0.0.1', '::1']);

it('keeps a remote device on its login page when OAuth is configured for localhost', function (string $provider) {
    config()->set("services.{$provider}.client_id", 'test-client');
    config()->set("services.{$provider}.client_secret", 'test-secret');

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.25', 'SERVER_ADDR' => '192.168.1.2'])
        ->get("http://192.168.1.2:8000/auth/{$provider}/redirect")
        ->assertRedirect('http://192.168.1.2:8000/login')
        ->assertSessionHasErrors([
            'social' => ucfirst($provider).' sign-in is currently available only on the server computer. To sign in from this device, the administrator must configure a shared HTTPS site address. You can still use your email and password.',
        ])
        ->assertSessionMissing('state');
})->with(['google', 'facebook']);

it('explains an unsupported Google callback origin before starting OAuth', function (string $origin) {
    config()->set('app.url', $origin);
    config()->set('services.google.client_id', 'test-client');
    config()->set('services.google.client_secret', 'test-secret');

    $this->get($origin.'/auth/google/redirect')
        ->assertRedirect($origin.'/login')
        ->assertSessionHasErrors([
            'social' => 'Google sign-in needs an HTTPS domain registered with Google. A local network IP address cannot be used. Please use your email and password until the administrator finishes setup.',
        ])
        ->assertSessionMissing('state');
})->with(['http://192.168.1.2:8000', 'https://192.168.1.2', 'http://autocare.example.com']);

it('starts Google OAuth over HTTPS forwarded by a local tunnel without a redirect loop', function () {
    config()->set('app.url', 'https://autocare.example.com');
    config()->set('services.google.redirect', 'https://autocare.example.com/auth/google/callback');
    config()->set('services.google.client_id', 'test-client');
    config()->set('services.google.client_secret', 'test-secret');

    $response = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->withHeaders([
            'X-Forwarded-Host' => 'autocare.example.com',
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Port' => '443',
        ])
        ->get('http://localhost:8000/auth/google/redirect');

    $response->assertRedirect()->assertSessionHas('state');
    expect(parse_url($response->headers->get('Location'), PHP_URL_HOST))->toBe('accounts.google.com');
    parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $parameters);
    expect($parameters['redirect_uri'])->toBe('https://autocare.example.com/auth/google/callback');
});

it('ignores forwarded headers from a device that is not a trusted proxy', function () {
    config()->set('services.google.client_id', 'test-client');
    config()->set('services.google.client_secret', 'test-secret');

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.25', 'SERVER_ADDR' => '192.168.1.2'])
        ->withHeaders([
            'X-Forwarded-Host' => 'localhost:8000',
            'X-Forwarded-Proto' => 'http',
            'X-Forwarded-For' => '127.0.0.1',
        ])
        ->get('http://192.168.1.2:8000/auth/google/redirect')
        ->assertRedirect('http://192.168.1.2:8000/login')
        ->assertSessionHasErrors('social')
        ->assertSessionMissing('state');
});

it('explains when a provider is not configured', function () {
    config()->set('services.google.client_id', null);
    config()->set('services.google.client_secret', null);

    $this->get(route('social.redirect', 'google'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['social' => 'Google sign-in is not configured yet. Please use your email and password.']);
});

it('registers a verified Google customer and signs them in', function () {
    Notification::fake();
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-123',
        'name' => 'Jamie Cruz',
        'email' => 'Jamie@Example.test',
        'email_verified' => true,
    ]));

    $this->get(route('social.callback', 'google'))->assertRedirect(route('social.registration'));
    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'jamie@example.test']);
    $this->post(route('social.registration.store'))->assertSessionHasErrors('legal_consent');
    $this->post(route('social.registration.store'), ['legal_consent' => 1])->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'jamie@example.test')->firstOrFail();
    $this->assertAuthenticatedAs($user);
    expect($user->role)->toBe('customer');
    expect($user->hasVerifiedEmail())->toBeTrue();
    $this->assertDatabaseHas('social_accounts', [
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_user_id' => 'google-123',
    ]);
    Notification::assertNothingSent();
});

it('sends email verification to a new Facebook customer', function () {
    Notification::fake();
    Socialite::fake('facebook', SocialiteUser::fake([
        'id' => 'facebook-123',
        'name' => 'Alex Reyes',
        'email' => 'alex@example.test',
    ]));

    $this->get(route('social.callback', 'facebook'))->assertRedirect(route('social.registration'));
    $this->post(route('social.registration.store'), ['legal_consent' => 1])->assertRedirect(route('verification.notice'));

    $user = User::query()->where('email', 'alex@example.test')->firstOrFail();
    $this->assertAuthenticatedAs($user);
    expect($user->role)->toBe('customer');
    expect($user->hasVerifiedEmail())->toBeFalse();
    $this->assertDatabaseHas('social_accounts', [
        'user_id' => $user->id,
        'provider' => 'facebook',
        'provider_user_id' => 'facebook-123',
    ]);
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('signs in a returning linked account without creating another user', function () {
    $user = User::factory()->create(['role' => 'customer']);
    SocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_user_id' => 'google-123',
    ]);
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-123',
        'email' => $user->email,
    ]));

    $this->get(route('social.callback', 'google'))->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('social_accounts', 1);
});

it('requires email verification code after a linked admin signs in with Google', function () {
    Notification::fake();
    $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
    SocialAccount::create([
        'user_id' => $admin->id,
        'provider' => 'google',
        'provider_user_id' => 'google-admin',
    ]);
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-admin',
        'email' => $admin->email,
    ]));

    $this->get(route('social.callback', 'google'))->assertRedirect(route('admin.two-factor.create'));
    $this->get(route('admin.dashboard'))->assertRedirect(route('admin.two-factor.create'));
    Notification::assertSentTo($admin, ImportantEventMail::class);
});

it('does not silently link a provider to an existing email account', function () {
    $user = User::factory()->create(['email' => 'owner@example.test', 'role' => 'shop_owner']);
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-other',
        'email' => $user->email,
        'email_verified' => true,
    ]));

    $this->get(route('social.callback', 'google'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['social' => 'An account already uses this email address. Sign in with your password and confirm the connection below to use Google next time.'])
        ->assertSessionHas('pending_social_link');

    $this->assertGuest();
    $this->assertDatabaseCount('social_accounts', 0);
});

it('connects a provider to an existing shop account only after password sign-in', function () {
    $owner = User::factory()->create([
        'email' => 'owner@example.test',
        'password' => 'CorrectPassword123!',
        'role' => 'shop_owner',
    ]);
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-owner',
        'email' => $owner->email,
        'email_verified' => true,
    ]));

    $this->get(route('social.callback', 'google'))->assertRedirect(route('login'));
    $this->post(route('login'), ['email' => $owner->email, 'password' => 'WrongPassword'])
        ->assertSessionHasErrors('email');
    $this->assertDatabaseCount('social_accounts', 0);

    $this->post(route('login'), ['email' => $owner->email, 'password' => 'CorrectPassword123!', 'connect_social' => 1])
        ->assertRedirect(route('shop.dashboard'))
        ->assertSessionHas('status', 'Google sign-in is now connected to your account.');

    $this->assertDatabaseHas('social_accounts', [
        'user_id' => $owner->id,
        'provider' => 'google',
        'provider_user_id' => 'google-owner',
    ]);
});

it('does not connect a provider when password sign-in omits explicit consent', function () {
    $user = User::factory()->create([
        'email' => 'customer@example.test',
        'password' => 'CorrectPassword123!',
    ]);
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-customer',
        'email' => $user->email,
        'email_verified' => true,
    ]));

    $this->get(route('social.callback', 'google'))->assertRedirect(route('login'));
    $this->get(route('login'))
        ->assertSee('name="connect_social"', false)
        ->assertSee('Google to this account after signing in');

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'CorrectPassword123!',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseCount('social_accounts', 0);
});

it('does not connect a pending provider to a different account', function () {
    $owner = User::factory()->create(['email' => 'owner@example.test']);
    $other = User::factory()->create([
        'email' => 'other@example.test',
        'password' => 'CorrectPassword123!',
    ]);
    Socialite::fake('facebook', SocialiteUser::fake([
        'id' => 'facebook-owner',
        'email' => $owner->email,
    ]));

    $this->get(route('social.callback', 'facebook'))->assertRedirect(route('login'));
    $this->post(route('login'), ['email' => $other->email, 'password' => 'CorrectPassword123!', 'connect_social' => 1])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($other);
    $this->assertDatabaseCount('social_accounts', 0);
});

it('does not connect an expired pending provider', function () {
    $user = User::factory()->create(['password' => 'CorrectPassword123!']);

    $this->withSession(['pending_social_link' => [
        'provider' => 'google',
        'provider_user_id' => 'google-old',
        'email' => $user->email,
        'created_at' => now()->subMinutes(11)->timestamp,
    ]])->post(route('login'), [
        'email' => $user->email,
        'password' => 'CorrectPassword123!',
        'connect_social' => 1,
    ])->assertRedirect(route('dashboard'));

    $this->assertDatabaseCount('social_accounts', 0);
});

it('refuses social sign-in for a suspended account', function () {
    $user = User::factory()->create(['status' => 'suspended']);
    SocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'facebook',
        'provider_user_id' => 'facebook-123',
    ]);
    Socialite::fake('facebook', SocialiteUser::fake(['id' => 'facebook-123']));

    $this->get(route('social.callback', 'facebook'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['social' => 'This account is unavailable. Please contact support.']);

    $this->assertGuest();
});

it('rejects a provider response without an email address', function () {
    Socialite::fake('facebook', SocialiteUser::fake([
        'id' => 'facebook-no-email',
        'email' => null,
    ]));

    $this->get(route('social.callback', 'facebook'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('social');

    $this->assertGuest();
    $this->assertDatabaseCount('users', 0);
});

it('shows a recoverable error when the provider sign-in is cancelled', function () {
    Socialite::fake('facebook', function (): never {
        throw new RuntimeException('Authorization declined');
    });

    $this->get(route('social.callback', 'facebook'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('social');

    $this->assertGuest();
    $this->assertDatabaseCount('users', 0);
});

it('explains how to retry when the OAuth callback loses its session state', function () {
    Socialite::fake('google', function (): never {
        throw new InvalidStateException;
    });

    $this->get(route('social.callback', 'google'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors([
            'social' => 'Your sign-in session expired or the site address changed. Open '.rtrim((string) config('app.url'), '/').' and try again with cookies enabled.',
        ]);

    $this->assertGuest();
    $this->assertDatabaseCount('users', 0);
});

it('rejects unsupported providers before opening an OAuth session', function () {
    $this->get('/auth/unknown/redirect')->assertNotFound();
    $this->get('/auth/unknown/callback')->assertNotFound();
});
