<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('registers a customer and redirects to the dashboard', function () {
    $response = $this->post('/register', [
        'name' => 'Jamie Cruz',
        'email' => 'jamie@example.test',
        'role' => 'customer',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertDatabaseHas('users', ['email' => 'jamie@example.test', 'role' => 'customer']);
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
