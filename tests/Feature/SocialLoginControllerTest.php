<?php

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

it('shows Google and Facebook sign-in on the login and registration pages', function () {
    $this->get(route('login'))->assertSee(route('social.redirect', 'google'))
        ->assertSee(route('social.redirect', 'facebook'));
    $this->get(route('register'))->assertSee(route('social.redirect', 'google'))
        ->assertSee(route('social.redirect', 'facebook'));
});

it('redirects to a configured provider', function (string $provider) {
    config()->set("services.{$provider}.client_id", 'test-client');
    config()->set("services.{$provider}.client_secret", 'test-secret');
    Socialite::fake($provider);

    $this->get(route('social.redirect', $provider))
        ->assertRedirect("https://socialite.fake/{$provider}/authorize");
})->with(['google', 'facebook']);

it('includes a session-backed state token in the Google authorization request', function () {
    config()->set('services.google.client_id', 'test-client');
    config()->set('services.google.client_secret', 'test-secret');

    $response = $this->get(route('social.redirect', 'google'));

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
    config()->set('services.google.client_id', 'test-client');
    config()->set('services.google.client_secret', 'test-secret');

    $response = $this->withServerVariables(['HTTP_HOST' => 'unexpected.example'])
        ->get('/auth/google/redirect');
    parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $parameters);

    expect($parameters['redirect_uri'])->toBe(rtrim(config('app.url'), '/').'/auth/google/callback');
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

    $this->get(route('social.callback', 'google'))->assertRedirect(route('dashboard'));

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

    $this->get(route('social.callback', 'facebook'))->assertRedirect(route('verification.notice'));

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
