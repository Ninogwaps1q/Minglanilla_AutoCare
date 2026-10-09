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
