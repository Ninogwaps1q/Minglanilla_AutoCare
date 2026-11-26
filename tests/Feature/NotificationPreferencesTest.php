<?php

use App\Jobs\SendEventSms;
use App\Models\User;
use App\Notifications\ImportantEventMail;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('keeps a customer’s selected channels visible when the SMS number needs correction', function () {
    $user = User::factory()->create(['role' => 'customer']);
    $url = route('preferences.notifications.edit');
    $this->actingAs($user)->from($url)->put(route('preferences.notifications.update'), [
        'sms_phone' => '12345', 'sms_consent' => '1', 'sms' => ['estimate' => '1'],
    ])->assertRedirect($url)->assertSessionHasErrors('sms_phone');

    $page = $this->get($url)->assertOk();
    expect($page->getContent())
        ->toMatch('/<input[^>]+id="sms-estimate"[^>]*\bchecked\b/')
        ->not->toMatch('/<input[^>]+id="email-estimate"[^>]*\bchecked\b/');
});

it('keeps email on by default and honors opt-in and event choices', function () {
    Bus::fake();
    Notification::fake();
    config()->set('services.semaphore.api_key', 'sms-key-for-tests');
    $user = User::factory()->create(['role' => 'customer', 'phone' => '09171234567']);
    $record = ['type' => 'quotation_sent', 'title' => 'Estimate', 'message' => 'Check your estimate.'];

    $user->notifications()->create($record);
    Notification::assertSentToTimes($user, ImportantEventMail::class, 1);
    Bus::assertNotDispatched(SendEventSms::class);

    $this->actingAs($user)->put(route('preferences.notifications.update'), [
        'sms_consent' => 1, 'sms_phone' => '12345',
    ])->assertSessionHasErrors('sms_phone');

    $this->actingAs($user)->put(route('preferences.notifications.update'), [
        'sms_consent' => 1, 'sms' => ['estimate' => 1], 'email' => ['booking' => 1],
    ])->assertRedirect();
    $user->notifications()->create($record);
    $user->notifications()->create(['type' => 'repair_status', 'title' => 'Started', 'message' => 'Work started', 'data' => ['status' => 'repairing']]);
    Notification::assertSentToTimes($user, ImportantEventMail::class, 1);
    Bus::assertNotDispatched(SendEventSms::class);
    expect($user->fresh()->hasConsentFor('sms'))->toBeTrue();
});

it('normalizes Philippine numbers and sends each important SMS at most once within the daily limit', function () {
    Bus::fake();
    Notification::fake();
    config()->set(['services.semaphore.api_key' => 'sms-key-for-tests', 'services.semaphore.daily_limit' => 1]);
    Http::fake(['api.semaphore.co/*' => Http::response([['message_id' => 42, 'status' => 'Queued']])]);
    $user = User::factory()->create(['phone' => '+63 (917) 123-4567']);
    $user->recordOptionalConsent('sms', true);
    $record = ['type' => 'quotation_sent', 'title' => 'Update', 'message' => 'Private pickup at 22 Sample Street.'];
    $first = $user->notifications()->create($record);
    $second = $user->notifications()->create($record);
    $sms = app(SmsService::class);

    (new SendEventSms($first->id))->handle($sms);
    (new SendEventSms($first->id))->handle($sms);
    (new SendEventSms($second->id))->handle($sms);

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request['number'] === '639171234567'
        && str_contains($request['message'], 'estimate') && ! str_contains($request['message'], 'Sample Street'));
    $this->assertDatabaseHas('sms_deliveries', ['notification_id' => $first->id, 'status' => 'accepted']);
    $this->assertDatabaseMissing('sms_deliveries', ['notification_id' => $second->id]);
});

it('records a provider error and stops delivery after consent is withdrawn', function () {
    Bus::fake();
    Notification::fake();
    config()->set('services.semaphore.api_key', 'sms-key-for-tests');
    Http::fake(['api.semaphore.co/*' => Http::response(['error' => 'Unavailable'], 503)]);
    $user = User::factory()->create(['phone' => '09171234567']);
    $user->recordOptionalConsent('sms', true);
    $record = ['type' => 'quotation_sent', 'title' => 'Estimate', 'message' => 'An estimate is available.'];
    $first = $user->notifications()->create($record);
    (new SendEventSms($first->id))->handle(app(SmsService::class));
    $this->assertDatabaseHas('sms_deliveries', ['notification_id' => $first->id, 'status' => 'failed']);

    $second = $user->notifications()->create($record);
    $user->recordOptionalConsent('sms', false);
    (new SendEventSms($second->id))->handle(app(SmsService::class));
    Http::assertSentCount(1);
});

it('records push consent and stores or removes a browser subscription', function () {
    config()->set('services.web_push.public_key', 'test-public-key');
    $user = User::factory()->create(['role' => 'customer']);
    $payload = [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
        'keys' => ['p256dh' => str_repeat('a', 87), 'auth' => str_repeat('a', 22)],
    ];
    config()->set(['services.web_push.private_key' => null, 'services.web_push.subject' => null]);
    $this->actingAs($user)->postJson(route('preferences.push.store'), $payload)->assertServiceUnavailable();
    config()->set(['services.web_push.private_key' => 'test-private-key', 'services.web_push.subject' => 'mailto:test@example.test']);
    $this->actingAs($user)->postJson(route('preferences.push.store'), $payload)->assertNoContent();
    $this->assertDatabaseHas('push_subscriptions', ['endpoint_hash' => hash('sha256', $payload['endpoint']), 'user_id' => $user->id]);
    expect($user->fresh()->hasConsentFor('push'))->toBeTrue();
    $this->postJson(route('preferences.push.store'), ['endpoint' => 'http://localhost'] + $payload)->assertJsonValidationErrors('endpoint');
    $this->postJson(route('preferences.push.store'), ['endpoint' => 'https://example.test/push'] + $payload)->assertJsonValidationErrors('endpoint');
    $this->deleteJson(route('preferences.push.destroy'))->assertNoContent();
    $this->assertDatabaseCount('push_subscriptions', 0);
    expect($user->fresh()->hasConsentFor('push'))->toBeFalse();
});
