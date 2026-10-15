<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('provisions an administrator using an interactive password prompt', function () {
    $this->artisan('app:provision-admin')
        ->expectsQuestion('Administrator name', 'Site Administrator')
        ->expectsQuestion('Administrator email', 'admin@example.test')
        ->expectsQuestion('Administrator password (minimum 12 characters)', 'A-Strong-Password-123')
        ->expectsOutput('Administrator account created.')
        ->assertSuccessful();

    $administrator = User::query()->where('email', 'admin@example.test')->firstOrFail();
    expect($administrator->role)->toBe('admin')
        ->and($administrator->hasVerifiedEmail())->toBeTrue()
        ->and($administrator->password)->not->toBe('A-Strong-Password-123');
});

it('rejects duplicate administrator emails', function () {
    User::factory()->create(['email' => 'admin@example.test']);

    $this->artisan('app:provision-admin')
        ->expectsQuestion('Administrator name', 'Another Administrator')
        ->expectsQuestion('Administrator email', 'admin@example.test')
        ->expectsQuestion('Administrator password (minimum 12 characters)', 'A-Strong-Password-123')
        ->expectsOutput('An account with this email already exists.')
        ->assertFailed();

    expect(User::query()->where('email', 'admin@example.test')->count())->toBe(1);
});
