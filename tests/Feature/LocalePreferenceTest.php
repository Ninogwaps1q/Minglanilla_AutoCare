<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('uses English by default and remembers the browser selection', function () {
    $this->get(route('privacy.policy'))->assertOk()->assertSee('lang="en"', false)->assertSee('Privacy Policy');
    $this->from(route('privacy.policy'))->post(route('language.update'), ['locale' => 'ceb'])
        ->assertRedirect(route('privacy.policy'))->assertCookie('autocare_locale', 'ceb');
    $this->withCookie('autocare_locale', 'ceb')->get(route('privacy.policy'))->assertOk()
        ->assertSee('lang="ceb"', false)->assertSee('Palisiya sa pribasiya');
});

it('stores a supported account locale and applies it to the customer workspace', function () {
    $user = User::factory()->create(['role' => 'customer']);
    $this->actingAs($user)->post(route('language.update'), ['locale' => 'fil'])->assertRedirect();
    expect($user->fresh()->locale)->toBe('fil');

    $this->get(route('customer.appointments.create'))->assertOk()
        ->assertSee('Mag-book ng serbisyo')
        ->assertSee('aria-label="Mabilis na nabigasyon"', false);
    $user->notifications()->create([
        'type' => 'appointment_status', 'title' => 'Appointment status updated',
        'message' => 'Your appointment is now confirmed.', 'data' => ['status' => 'confirmed'],
    ]);
    $this->get(route('customer.notifications.index'))->assertOk()->assertSee('Ang appointment mo ay kumpirmado na.');
    $this->post(route('language.update'), ['locale' => 'unknown'])->assertSessionHasErrors('locale');
    expect($user->fresh()->locale)->toBe('fil');
});
