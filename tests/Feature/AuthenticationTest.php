<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

it('registers a customer and sends a verification email', function () {
    Notification::fake();

    $response = $this->post('/register', [
        'name' => 'Jamie Cruz',
        'email' => 'jamie@example.test',
        'role' => 'customer',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertRedirect(route('verification.notice'));
    $this->assertDatabaseHas('users', ['email' => 'jamie@example.test', 'role' => 'customer']);
    $user = User::where('email', 'jamie@example.test')->firstOrFail();
    $this->assertAuthenticatedAs($user);
    expect($user->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('keeps unverified accounts out of protected workspaces', function (string $role, string $route) {
    $user = User::factory()->unverified()->create(['role' => $role]);

    $this->actingAs($user)->get(route($route))->assertRedirect(route('verification.notice'));
})->with([
    'customer' => ['customer', 'dashboard'],
    'shop owner' => ['shop_owner', 'shop.dashboard'],
    'administrator' => ['admin', 'admin.dashboard'],
]);

it('resends verification mail only to unverified authenticated accounts', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $this->post(route('verification.send'))->assertRedirect(route('login'));
    $this->actingAs($user)->post(route('verification.send'))->assertSessionHas('status');
    Notification::assertSentToTimes($user, VerifyEmail::class, 1);

    $user->markEmailAsVerified();
    $this->post(route('verification.send'))->assertRedirect(route('dashboard'));
    Notification::assertSentToTimes($user, VerifyEmail::class, 1);
});

it('verifies an email through a signed link and rejects tampering', function () {
    $user = User::factory()->unverified()->create();
    $verificationUrl = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->getKey(),
        'hash' => sha1($user->getEmailForVerification()),
    ]);

    $this->actingAs($user)->get($verificationUrl.'&tampered=yes')->assertForbidden();
    $this->get($verificationUrl)->assertRedirect(route('dashboard'));
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('sends an unverified account to the verification page after login', function () {
    $user = User::factory()->unverified()->create(['password' => 'CorrectPassword123!']);

    $this->post(route('login'), ['email' => $user->email, 'password' => 'CorrectPassword123!'])
        ->assertRedirect(route('verification.notice'));
});

it('forbids a customer from accessing the admin dashboard', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer)->get('/admin/dashboard')->assertForbidden();
});

it('redirects each account role to its own workspace after login', function (string $role, string $route) {
    $user = User::factory()->create(['role' => $role, 'password' => 'CorrectPassword123!']);

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'CorrectPassword123!',
    ]);

    $response->assertRedirect(route($route));
    $this->assertAuthenticatedAs($user);
})->with([
    'customer' => ['customer', 'dashboard'],
    'shop owner' => ['shop_owner', 'shop.dashboard'],
    'administrator' => ['admin', 'admin.dashboard'],
]);

it('does not authenticate an unknown account or an incorrect password', function (array $credentials) {
    User::factory()->create(['email' => 'known@example.test', 'password' => 'CorrectPassword123!']);

    $response = $this->from(route('login'))->post(route('login'), $credentials);

    $response->assertRedirect(route('login'))
        ->assertInvalid(['email' => 'Incorrect email address or password. Please check your details and try again.']);
    $this->assertGuest();
})->with([
    'unknown account' => [[
        'email' => 'missing@example.test',
        'password' => 'CorrectPassword123!',
    ]],
    'incorrect password' => [[
        'email' => 'known@example.test',
        'password' => 'WrongPassword123!',
    ]],
]);

it('shows readable login validation messages', function (array $credentials, string $field, string $message) {
    $response = $this->from(route('login'))->post(route('login'), $credentials);

    $response->assertRedirect(route('login'))->assertInvalid([$field => $message]);
    $this->assertGuest();
})->with([
    'email is missing' => [['password' => 'Password123!'], 'email', 'Enter your email address.'],
    'email is invalid' => [['email' => 'not-an-email', 'password' => 'Password123!'], 'email', 'Enter a valid email address, such as name@example.com.'],
    'password is missing' => [['email' => 'driver@example.test'], 'password', 'Enter your password.'],
]);

it('redirects a shop owner away from the generic dashboard', function () {
    $owner = User::factory()->create(['role' => 'shop_owner']);

    $this->actingAs($owner)->get(route('dashboard'))->assertRedirect(route('shop.dashboard'));
});
