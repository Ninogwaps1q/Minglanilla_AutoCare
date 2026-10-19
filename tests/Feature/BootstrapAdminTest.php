<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('creates only the first administrator with a verified email and hashed password', function () {
    $this->artisan('app:bootstrap-admin')
        ->expectsQuestion('Administrator name', 'Site Administrator')
        ->expectsQuestion('Administrator email', 'admin@example.test')
        ->expectsQuestion('Administrator password', 'StrongPassword123!')
        ->expectsQuestion('Confirm administrator password', 'StrongPassword123!')
        ->assertExitCode(0);

    $admin = User::query()->where('email', 'admin@example.test')->firstOrFail();
    expect($admin->role)->toBe('admin');
    expect($admin->email_verified_at)->not->toBeNull();
    expect(Hash::check('StrongPassword123!', $admin->password))->toBeTrue();

    $this->artisan('app:bootstrap-admin')->assertExitCode(1);
    $this->assertDatabaseCount('users', 1);
});

it('rejects weak administrator credentials before creating an account', function () {
    $this->artisan('app:bootstrap-admin')
        ->expectsQuestion('Administrator name', 'Site Administrator')
        ->expectsQuestion('Administrator email', 'admin@example.test')
        ->expectsQuestion('Administrator password', 'password')
        ->expectsQuestion('Confirm administrator password', 'password')
        ->assertExitCode(1);

    $this->assertDatabaseCount('users', 0);
});
