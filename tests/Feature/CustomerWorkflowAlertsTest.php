<?php

use App\Jobs\DeliverWorkflowAlert;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\RepairJob;
use App\Models\Shop;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use App\Models\WorkflowAlert;
use App\Notifications\Workflow\WorkflowInboxChannel;
use App\Notifications\Workflow\WorkflowPushChannel;
use App\Notifications\Workflow\WorkflowSmsChannel;
use App\Notifications\WorkflowEvent;
use App\Services\WebPushCrypto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('sends each verified workflow transition through the four chosen channels once', function () {
    Notification::fake();
    config()->set([
        'services.semaphore.api_key' => 'fake-key',
        'services.web_push.public_key' => 'test-public',
        'services.web_push.private_key' => 'test-private',
        'services.web_push.subject' => 'mailto:test@example.test',
    ]);
    [$customer, $shop, $job] = workflowAlertFixture();
    $customer->recordOptionalConsent('sms', true);
    $customer->recordOptionalConsent('push', true);

    workflowTransition($job, 'pending', 'diagnosis');
    DB::transaction(function () use ($job, $shop): void {
        $inspection = VehicleInspection::query()->create([
            'vehicle_id' => $job->vehicle_id, 'shop_id' => $shop->id, 'repair_job_id' => $job->id,
            'inspection_date' => today(), 'overall_condition' => 'Needs Attention',
        ]);
        $inspection->items()->create(['category' => 'Brakes', 'item_name' => 'Pads', 'condition' => 'Needs Attention']);
    });
    $quote = workflowEstimate($job);
    workflowTransition($job, 'waiting_approval', 'approved');
    workflowTransition($job, 'approved', 'repairing');
    workflowTransition($job, 'repairing', 'waiting_parts');
    workflowTransition($job, 'waiting_parts', 'repairing');
    workflowTransition($job, 'repairing', 'completed');
    $invoice = workflowInvoice($job);
    $payment = $invoice->payments()->create([
        'customer_id' => $customer->id, 'shop_id' => $shop->id, 'amount' => 600,
        'payment_method' => 'paymongo', 'payment_date' => today(), 'status' => 'checkout_pending',
    ]);
    $payment->update(['status' => 'failed']);
    $payment->update(['status' => 'failed']);
    $payment->update(['status' => 'confirmed']);
    $invoice->update(['status' => 'paid']);
    $payment->update(['refund_status' => 'succeeded']);
    workflowTransition($job, 'completed', 'released');

    $events = array_filter(WorkflowEvent::cases(), fn (WorkflowEvent $event) => $event !== WorkflowEvent::EstimateReminder);
    foreach ($events as $event) {
        $record = WorkflowAlert::query()->where('event', $event->value)->sole();
        (new DeliverWorkflowAlert($record->id))->handle();
        Notification::assertSentToTimes($customer, $event->notificationClass(), 4);
        foreach ([WorkflowInboxChannel::class, 'mail', WorkflowSmsChannel::class, WorkflowPushChannel::class] as $channel) {
            Notification::assertSentTo($customer, $event->notificationClass(), fn ($notification, array $channels) => $channels === [$channel]);
        }
    }
    expect(WorkflowAlert::query()->where('event', 'inspection_results')->sole()->data['problems'])->toBe(1);
    expect(WorkflowAlert::query()->where('event', 'estimate_ready')->sole()->destination())->toBe(route('customer.quotations.index', ['focus' => $quote->id]));
});

it('resolves channel consent independently when delivering queued customer alerts', function () {
    Notification::fake();
    config()->set([
        'services.semaphore.api_key' => 'fake-key', 'services.web_push.public_key' => 'test-public',
        'services.web_push.private_key' => 'test-private', 'services.web_push.subject' => 'mailto:test@example.test',
    ]);
    [$customer, , $job] = workflowAlertFixture();
    $customer->forceFill(['communication_preferences' => ['email' => ['inspection' => false], 'sms' => ['estimate' => false]]])->save();
    $customer->recordOptionalConsent('sms', true);

    workflowTransition($job, 'pending', 'diagnosis');
    $revised = workflowEstimate($job, revised: true);

    Notification::assertSentToTimes($customer, WorkflowEvent::InspectionStarted->notificationClass(), 2);
    Notification::assertSentTo($customer, WorkflowEvent::InspectionStarted->notificationClass(), fn ($notification, array $channels) => $channels === [WorkflowSmsChannel::class]);
    Notification::assertSentToTimes($customer, WorkflowEvent::EstimateReady->notificationClass(), 2);
    Notification::assertSentTo($customer, WorkflowEvent::EstimateReady->notificationClass(), fn ($notification, array $channels) => $channels === ['mail']);
    Notification::assertSentTo($customer, WorkflowEvent::EstimateReady->notificationClass(), fn ($notification) => $notification->title($customer) === 'Updated estimate needs your approval'
        && str_contains($notification->alert->destination(), "focus={$revised->id}"));
});

it('keeps checklist results and email available if SMS and browser push both fail', function () {
    [$customer, $shop, $job] = workflowAlertFixture();
    $customer->recordOptionalConsent('sms', true);
    $customer->recordOptionalConsent('push', true);
    workflowPushSubscription($customer);
    Storage::fake('local');
    config()->set('services.semaphore.api_key', 'fake-key');
    Http::preventStrayRequests();
    Http::fake([
        'https://api.semaphore.co/*' => Http::response(['error' => 'Provider unavailable'], 503),
        'https://fcm.googleapis.com/*' => Http::response([], 503),
    ]);
    $payload = [
        'inspection_date' => today()->toDateString(), 'overall_condition' => 'Needs Attention',
        'items' => [['category' => 'Brakes', 'item_name' => 'Front pads', 'has_problem' => true]],
    ];

    $this->actingAs($shop->owner)->post(route('shop.repair-photos.store', $job), [
        'stage' => 'damage',
        'photo' => UploadedFile::fake()->createWithContent('damage.png', file_get_contents(public_path('icons/icon-192.png'))),
    ])->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('shop.inspections.store', $job), $payload)->assertRedirect(route('shop.repair-jobs.index'));
    $this->post(route('shop.inspections.store', $job), $payload)->assertRedirect();
    $alert = WorkflowAlert::query()->sole();
    expect($alert->channels)->toMatchArray(['WorkflowInboxChannel' => 'processed', 'mail' => 'processed', 'WorkflowPushChannel' => 'failed']);
    $this->assertDatabaseHas('sms_deliveries', ['workflow_alert_id' => $alert->id, 'status' => 'failed']);
    Http::assertSentCount(2);
    expect($customer->notifications()->sole()->message)->toContain('Problems found: 1', 'Photos on file: 1', 'Front pads');
    $this->actingAs(User::factory()->create(['role' => 'customer']))->get(route('customer.workflow-alert.open', $alert))->assertNotFound();
    $this->actingAs($customer)->get(route('customer.workflow-alert.open', $alert))->assertRedirect($alert->destination());
    $this->get(route('repair-photos.show', $job->photos()->sole()))->assertOk();
    expect($customer->notifications()->sole()->read_at)->not->toBeNull();
});

it('honors the daily deliverable SMS limit and records only safe provider fields and optional cost', function () {
    [$customer, , $job] = workflowAlertFixture();
    $customer->recordOptionalConsent('sms', true);
    config()->set(['services.semaphore.api_key' => 'fake-key', 'services.semaphore.daily_limit' => 1]);
    Http::fake(['https://api.semaphore.co/*' => Http::response([['message_id' => 123, 'status' => 'Queued', 'cost' => 0.56, 'recipient' => 'private']])]);

    workflowTransition($job, 'pending', 'diagnosis');
    workflowEstimate($job);
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request['number'] === '639171234567'
        && strlen($request['message']) <= 160
        && str_contains($request['message'], 'Digos Garage')
        && str_contains($request['message'], 'Toyota Vios')
        && str_contains($request['message'], 'ABC 1234')
        && str_contains($request['message'], '/n/'));
    $record = DB::table('sms_deliveries')->sole();
    expect((float) $record->cost)->toBe(0.56)
        ->and(json_decode($record->provider_response, true))->toBe(['http_status' => 200, 'message_id' => '123', 'status' => 'queued']);

    $this->travel(1)->day();
    workflowTransition($job, 'approved', 'repairing');
    Http::assertSentCount(2);
});

it('encrypts the browser payload, signs for the provider origin and removes expired subscriptions', function () {
    [$customer, , $job] = workflowAlertFixture();
    $customer->recordOptionalConsent('push', true);
    [$vapidKey, $browserKey, $auth] = workflowPushSubscription($customer);
    Http::preventStrayRequests();
    Http::fake(['https://fcm.googleapis.com/*' => Http::sequence()->push('', 201)->push('', 410)]);

    workflowEstimate($job);
    $request = Http::recorded()->first()[0];
    expect($request->hasHeader('Content-Encoding', 'aes128gcm'))->toBeTrue();
    $encoded = trim(explode(',', $request->header('Authorization')[0])[0]);
    [$jwtHeader, $jwtBody, $jwtSignature] = explode('.', substr($encoded, strlen('vapid t=')));
    $decode = fn (string $value): string => base64_decode(strtr($value, '-_', '+/'));
    $claims = json_decode($decode($jwtBody), true, flags: JSON_THROW_ON_ERROR);
    expect($claims)->toMatchArray(['aud' => 'https://fcm.googleapis.com', 'sub' => 'mailto:test@example.test']);
    $signature = $decode($jwtSignature);
    $integer = static function (string $value): string {
        $value = ltrim($value, "\0");
        if (ord($value[0]) >= 128) {
            $value = "\0".$value;
        }

        return "\x02".chr(strlen($value)).$value;
    };
    $sequence = $integer(substr($signature, 0, 32)).$integer(substr($signature, 32));
    $der = "\x30".chr(strlen($sequence)).$sequence;
    expect(openssl_verify($jwtHeader.'.'.$jwtBody, $der, openssl_pkey_get_details($vapidKey)['key'], OPENSSL_ALGO_SHA256))->toBe(1);

    $body = $request->body();
    $salt = substr($body, 0, 16);
    expect(ord($body[20]))->toBe(65);
    $peerPoint = substr($body, 21, 65);
    $peerDer = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200').$peerPoint;
    $peerPem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($peerDer), 64, "\n")."-----END PUBLIC KEY-----\n";
    $secret = openssl_pkey_derive($peerPem, $browserKey, 32);
    $browserPoint = app(WebPushCrypto::class)->publicPoint($browserKey);
    $ikm = hash_hmac('sha256', "WebPush: info\0".$browserPoint.$peerPoint."\x01", hash_hmac('sha256', $secret, $auth, true), true);
    $prk = hash_hmac('sha256', $ikm, $salt, true);
    $key = substr(hash_hmac('sha256', "Content-Encoding: aes128gcm\0\x01", $prk, true), 0, 16);
    $nonce = substr(hash_hmac('sha256', "Content-Encoding: nonce\0\x01", $prk, true), 0, 12);
    $plaintext = openssl_decrypt(substr($body, 86, -16), 'aes-128-gcm', $key, OPENSSL_RAW_DATA, $nonce, substr($body, -16));
    expect(substr($plaintext, -1))->toBe("\x02");
    $payload = json_decode(substr($plaintext, 0, -1), true, flags: JSON_THROW_ON_ERROR);
    expect($payload['body'])->toContain('Digos Garage · Toyota Vios (ABC 1234)');
    expect($payload['url'])->toBe(WorkflowAlert::query()->sole()->destination());

    workflowTransition($job, 'pending', 'diagnosis');
    $this->assertDatabaseCount('push_subscriptions', 0);
    Http::assertSentCount(2);
});

it('reminds an unanswered estimate at 24 and 48 hours and stops when the customer decides', function () {
    Notification::fake();
    [$customer, , $job] = workflowAlertFixture();
    $estimate = workflowEstimate($job);
    $answeredEstimate = workflowEstimate($job);

    $this->travel(23)->hours();
    $this->artisan('app:dispatch-workflow-alerts')->assertSuccessful();
    $this->assertDatabaseMissing('workflow_alerts', ['event' => 'estimate_reminder']);

    $this->travel(1)->hour();
    $this->artisan('app:dispatch-workflow-alerts')->assertSuccessful();
    $this->artisan('app:dispatch-workflow-alerts')->assertSuccessful();
    expect(WorkflowAlert::query()->where('event', 'estimate_reminder')->count())->toBe(2);
    $answeredEstimate->update(['status' => 'accepted']);

    $this->travel(24)->hours();
    $this->artisan('app:dispatch-workflow-alerts')->assertSuccessful();
    $this->assertDatabaseHas('workflow_alerts', ['event_key' => "estimate:{$estimate->id}:48"]);
    $this->assertDatabaseMissing('workflow_alerts', ['event_key' => "estimate:{$answeredEstimate->id}:48"]);
    Notification::assertSentToTimes($customer, WorkflowEvent::EstimateReminder->notificationClass(), 6);
});

it('discards alerts for a transaction that rolled back', function () {
    Notification::fake();
    [, , $job] = workflowAlertFixture();
    expect(fn () => DB::transaction(function () use ($job): void {
        workflowTransition($job, 'approved', 'repairing');
        throw new RuntimeException('Rolled back');
    }))->toThrow(RuntimeException::class);
    $this->assertDatabaseCount('workflow_alerts', 0);
    Notification::assertNothingSent();
});

function workflowAlertFixture(): array
{
    $customer = User::factory()->create(['role' => 'customer', 'phone' => '09171234567']);
    $shop = Shop::factory()->create(['name' => 'Digos Garage', 'verification_status' => 'approved', 'shop_status' => 'active', 'plan_slug' => 'premium', 'plan_expires_at' => now()->addMonth()]);
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id, 'make' => 'Toyota', 'model' => 'Vios', 'plate_number' => 'ABC 1234']);
    $job = RepairJob::query()->create(['shop_id' => $shop->id, 'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'job_number' => 'WORKFLOW', 'status' => 'pending']);

    return [$customer, $shop, $job];
}

function workflowTransition(RepairJob $job, string $from, string $to): void
{
    $job->update(['status' => $to]);
    $job->statusHistories()->create(['from_status' => $from, 'to_status' => $to, 'changed_at' => now()]);
}

function workflowEstimate(RepairJob $job, bool $revised = false): Quotation
{
    return Quotation::query()->create([
        'shop_id' => $job->shop_id, 'customer_id' => $job->customer_id, 'vehicle_id' => $job->vehicle_id,
        'quotation_number' => fake()->unique()->numerify('EST-#####'), 'status' => 'sent',
        'subtotal' => 600, 'total' => 600, 'valid_until' => today()->addWeek(), 'is_supplemental' => $revised,
    ]);
}

function workflowInvoice(RepairJob $job): Invoice
{
    return Invoice::query()->create([
        'shop_id' => $job->shop_id, 'customer_id' => $job->customer_id, 'repair_job_id' => $job->id,
        'invoice_number' => 'INV-WORKFLOW', 'status' => 'unpaid', 'total' => 600, 'subtotal' => 600,
    ]);
}

function workflowPushSubscription(User $user): array
{
    $crypto = app(WebPushCrypto::class);
    $server = $crypto->generateKey();
    $browser = $crypto->generateKey();
    config()->set([
        'services.web_push.private_key' => $crypto->encode(str_pad(openssl_pkey_get_details($server)['ec']['d'], 32, "\0", STR_PAD_LEFT)),
        'services.web_push.public_key' => $crypto->encode($crypto->publicPoint($server)),
        'services.web_push.subject' => 'mailto:test@example.test',
    ]);
    $endpoint = 'https://fcm.googleapis.com/fcm/send/test-endpoint';
    $auth = random_bytes(16);
    DB::table('push_subscriptions')->insert([
        'user_id' => $user->id, 'endpoint_hash' => hash('sha256', $endpoint),
        'endpoint' => encrypt($endpoint), 'public_key' => $crypto->encode($crypto->publicPoint($browser)),
        'auth_secret' => $crypto->encode($auth), 'created_at' => now(), 'updated_at' => now(),
    ]);

    return [$server, $browser, $auth];
}
