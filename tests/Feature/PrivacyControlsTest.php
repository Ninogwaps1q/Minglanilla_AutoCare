<?php

use App\Models\PrivacyRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('publishes draft policies with the configured privacy contact', function () {
    config()->set('privacy.contact', 'privacy@example.test');

    $this->get(route('privacy.policy'))->assertOk()->assertSee('Draft for legal review')->assertSee('privacy@example.test');
    $this->get(route('privacy.terms'))->assertOk()->assertSee('Terms of Service')->assertSee('Draft for legal review');
});

it('requires and records the policy agreement before signing up', function () {
    $payload = [
        'name' => 'Jamie Cruz', 'email' => 'privacy-signup@example.test', 'role' => 'customer',
        'password' => 'Password123!', 'password_confirmation' => 'Password123!',
    ];
    $this->post(route('register'), $payload)->assertSessionHasErrors('legal_consent');
    $this->assertDatabaseMissing('users', ['email' => $payload['email']]);

    $this->post(route('register'), $payload + ['legal_consent' => 1])->assertRedirect(route('verification.notice'));
    $user = User::query()->whereEmail($payload['email'])->firstOrFail();
    expect($user->legal_accepted_at)->not->toBeNull()
        ->and($user->legal_version)->toBe(config('privacy.version'))
        ->and($user->hasConsentFor('sms'))->toBeFalse();
});

it('queues private data requests once and audits their review without deleting the account', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $admin = User::factory()->create(['role' => 'admin']);
    $requestUrl = route('customer.privacy-requests.store');

    $this->actingAs($customer)->post($requestUrl, ['type' => 'download'])->assertRedirect();
    $this->post($requestUrl, ['type' => 'delete'])->assertRedirect();
    $this->post($requestUrl, ['type' => 'delete'])->assertRedirect();
    $this->assertDatabaseCount('privacy_requests', 2);
    $this->get(route('admin.privacy-requests.index'))->assertForbidden();

    $item = PrivacyRequest::query()->where('type', 'delete')->firstOrFail();
    $this->actingAs($admin)->get(route('admin.privacy-requests.index'))->assertOk()->assertSee($customer->email);
    $this->patch(route('admin.privacy-requests.update', $item), ['status' => 'declined'])->assertSessionHasErrors('reason');
    $this->patch(route('admin.privacy-requests.update', $item), ['status' => 'declined', 'reason' => 'Open payment dispute'])->assertRedirect();
    $this->assertDatabaseHas('privacy_requests', ['id' => $item->id, 'status' => 'declined']);
    $this->assertDatabaseHas('users', ['id' => $customer->id, 'status' => 'active']);
    $this->assertDatabaseHas('activity_logs', ['user_id' => $admin->id, 'action' => 'resolved_privacy_request']);
});

it('records a location permission decision without coordinates', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $this->actingAs($customer)->postJson(route('privacy.location'), ['allowed' => true, 'latitude' => 14.6])->assertNoContent();

    expect($customer->fresh()->optional_consents['location'])->toHaveKeys(['allowed', 'version', 'at'])->not->toHaveKey('latitude');
    expect($customer->fresh()->hasConsentFor('location'))->toBeTrue();
    $this->postJson(route('privacy.location'), ['allowed' => false])->assertNoContent();
    expect($customer->fresh()->hasConsentFor('location'))->toBeFalse();
});
