<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('shows the forgot password and reset password forms', function () {
    $this->get(route('password.request'))->assertOk()->assertSee('Reset password');
    $this->get(route('password.reset', ['token' => 'test-token', 'email' => 'driver@example.test']))->assertOk()->assertSee('driver@example.test');
});

it('emails a reset link without exposing whether an account exists', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status');
    $knownStatus = session('status');
    $this->post(route('password.email'), ['email' => 'missing@example.test'])->assertSessionHas('status');
    $missingStatus = session('status');

    Notification::assertSentTo($user, ResetPassword::class);
    expect($knownStatus)->toBe($missingStatus);
});

it('resets a password with a valid token and rejects an invalid token', function () {
    Notification::fake();
    $user = User::factory()->create();
    $this->post(route('password.email'), ['email' => $user->email]);
    $token = null;
    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
        $token = $notification->token;

        return true;
    });

    $this->post(route('password.store'), ['token' => 'invalid', 'email' => $user->email, 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'])->assertSessionHasErrors('email');
    $this->post(route('password.store'), ['token' => $token, 'email' => $user->email, 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'])->assertRedirect(route('login'));

    expect(Hash::check('NewPassword123!', $user->fresh()->password))->toBeTrue();
});
