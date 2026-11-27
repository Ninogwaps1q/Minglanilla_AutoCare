<?php

use App\Jobs\SendConversationAlert;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\MessageThread;
use App\Models\PrivacyRequest;
use App\Models\Quotation;
use App\Models\RepairJob;
use App\Models\Service;
use App\Models\Shop;
use App\Models\ShopPromotion;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Messaging\MessageAlerts;
use App\Services\WebPushDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('streams scoped, spreadsheet-safe CSV files and audits the export', function () {
    Notification::fake();
    [$shop, $customer, $vehicle] = extensionFixture();
    $shop->update(['plan_slug' => 'basic']);
    $other = Shop::factory()->create();
    $ours = Invoice::query()->create(['shop_id' => $shop->id, 'customer_id' => $customer->id, 'invoice_number' => '=1+1', 'subtotal' => 150, 'total' => 150]);
    Invoice::query()->create(['shop_id' => $other->id, 'customer_id' => $customer->id, 'invoice_number' => 'PRIVATE-OTHER', 'subtotal' => 120, 'total' => 120]);
    $route = route('shop.reports.csv', ['dataset' => 'invoices', 'shop_id' => $other->id]);
    $this->actingAs($customer)->get($route)->assertForbidden();
    $csv = $this->actingAs($shop->owner)->get($route)->assertOk()->streamedContent();
    $this->get(route('shop.exports.index'))->assertOk()->assertSee('Download for Excel');
    expect($csv)->toStartWith("\xef\xbb\xbf")->toContain("'".$ours->invoice_number)->not->toContain('PRIVATE-OTHER');
    $this->assertDatabaseHas('activity_logs', ['action' => 'exported_csv', 'user_id' => $shop->owner_id]);

    $admin = User::factory()->create(['role' => 'admin']);
    $users = $this->actingAs($admin)->get(route('admin.reports.csv', 'users'))->assertOk()->streamedContent();
    expect($users)->toContain('customer')->not->toContain($customer->email, $customer->name);
});

it('recovers a removed vehicle and keeps its booking history and unique plate', function () {
    [$shop, $customer, $vehicle] = extensionFixture();
    $admin = User::factory()->create(['role' => 'admin']);
    $appointment = $customer->appointments()->create([
        'shop_id' => $shop->id, 'vehicle_id' => $vehicle->id, 'status' => 'completed',
        'appointment_date' => today()->subDay()->toDateString(), 'appointment_time' => '10:00',
    ]);
    $this->actingAs($customer)->delete(route('customer.vehicles.destroy', $vehicle))->assertRedirect();
    $this->assertSoftDeleted($vehicle);
    expect($appointment->fresh()->vehicle->plate_number)->toBe($vehicle->plate_number);
    $restore = route('admin.archived-records.update', ['type' => 'vehicles', 'record' => $vehicle->id]);
    $this->patch($restore, ['action' => 'restore', 'reason' => 'Wrong vehicle'])->assertForbidden();
    $this->actingAs($admin)->get(route('admin.archived-records.index', ['type' => 'vehicles']))->assertOk()->assertSee($vehicle->plate_number);
    $this->patch($restore, ['action' => 'restore'])->assertSessionHasErrors('reason');
    $this->patch($restore, ['action' => 'restore', 'reason' => 'Wrong vehicle'])->assertRedirect();
    expect($vehicle->fresh()->trashed())->toBeFalse();
    $this->assertDatabaseHas('activity_logs', ['user_id' => $admin->id, 'action' => 'restored_record']);
});

it('anonymizes a customer after approved privacy deletion while keeping billing history', function () {
    Notification::fake();
    [$shop, $customer, $vehicle] = extensionFixture();
    $admin = User::factory()->create(['role' => 'admin']);
    $invoice = Invoice::query()->create(['shop_id' => $shop->id, 'customer_id' => $customer->id, 'invoice_number' => 'KEEP-HISTORY', 'subtotal' => 100, 'total' => 100, 'status' => 'paid']);
    $request = PrivacyRequest::query()->create(['user_id' => $customer->id, 'type' => 'delete', 'status' => 'pending']);
    $this->actingAs($admin)->patch(route('admin.privacy-requests.update', $request), ['status' => 'completed', 'reason' => 'Privacy deletion approved'])
        ->assertRedirect()->assertSessionHasNoErrors();
    $this->assertSoftDeleted($customer);
    $this->assertSoftDeleted($vehicle);
    expect($invoice->fresh()->customer->name)->toBe('Deleted customer')
        ->and($invoice->fresh()->customer->email)->not->toBe($customer->email)
        ->and(Vehicle::withTrashed()->findOrFail($vehicle->id)->plate_number)->not->toBe($vehicle->plate_number);
    $this->patch(route('admin.archived-records.update', ['type' => 'users', 'record' => $customer->id]), ['action' => 'restore', 'reason' => 'Restore'])->assertForbidden();
});

it('groups private chat alerts, omits previews by default, and suppresses delivery while viewing', function () {
    Bus::fake([SendConversationAlert::class]);
    [$shop, $customer] = extensionFixture();
    $customer->recordOptionalConsent('push', true);
    $thread = MessageThread::query()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id]);

    $this->actingAs($shop->owner);
    foreach (['First private message', 'Second private message'] as $message) {
        $this->postJson(route('shop.messages.store', $thread), ['message' => $message])->assertCreated();
    }
    expect($customer->notifications()->count())->toBe(1);
    Bus::assertNotDispatched(SendConversationAlert::class, fn (SendConversationAlert $alert): bool => ! $alert->push);
    $push = $this->mock(WebPushDelivery::class);
    $push->shouldReceive('send')->once()->withArgs(fn (User $user, array $message): bool => $user->is($customer)
        && str_contains($message['url'], 'customer/messages') && ! str_contains($message['body'], 'private message'));
    $push->shouldReceive('configured')->andReturn(false);
    $job = new SendConversationAlert($thread->id, $customer->id, true);
    app()->call([$job, 'handle']);
    app()->call([$job, 'handle']);

    $this->actingAs($customer)->get(route('customer.messages.show', $shop))->assertOk();
    expect(app(MessageAlerts::class)->isViewing($thread->id, $customer->id))->toBeTrue();
    $this->actingAs($shop->owner)->postJson(route('shop.messages.store', $thread), ['message' => 'Third private message'])->assertCreated();
    $this->travel(61)->seconds();
    app(MessageAlerts::class)->viewed($thread, $customer);
    app()->call([$job, 'handle']);
});

it('sends an opted-in conversation digest without chat content and keeps a single SMS delivery', function () {
    Bus::fake([SendConversationAlert::class]);
    Http::fake(['api.semaphore.co/*' => Http::response([['message_id' => 8765, 'status' => 'Queued']])]);
    config()->set('services.semaphore.api_key', 'fake-key');
    [$shop, $customer] = extensionFixture();
    $customer->update(['phone' => '09171234567']);
    $customer->forceFill(['communication_preferences' => ['sms' => ['messages' => true]]])->save();
    $customer->recordOptionalConsent('sms', true);
    $thread = MessageThread::query()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id]);
    $this->actingAs($shop->owner)->postJson(route('shop.messages.store', $thread), ['message' => 'A private conversation detail.'])->assertCreated();
    Bus::assertDispatched(SendConversationAlert::class, fn (SendConversationAlert $job): bool => ! $job->push && $job->delay->greaterThan(now()->addMinute()));
    $job = new SendConversationAlert($thread->id, $customer->id, false);
    $this->travel(2)->minutes();
    app()->call([$job, 'handle']);
    app()->call([$job, 'handle']);
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request['number'] === '639171234567'
        && str_contains($request['message'], 'New message') && ! str_contains($request['message'], 'private conversation'));
    $this->assertDatabaseHas('sms_deliveries', ['user_id' => $customer->id, 'status' => 'accepted']);
});

it('enforces promotion ownership, keeps one use per customer, releases a cancellation, and applies the discount to the bill', function () {
    Notification::fake();
    [$shop, $customer, $vehicle] = extensionFixture();
    $service = Service::factory()->create(['estimated_duration' => 60]);
    $shop->services()->create(['service_id' => $service->id, 'price' => 800, 'availability' => true]);
    $promo = ShopPromotion::factory()->create(['code' => 'SAVE10', 'shop_id' => $shop->id,
        'customer_limit' => 1, 'service_ids' => [$service->id], 'minimum' => 500]);
    $payload = ['shop_id' => $shop->id, 'vehicle_id' => $vehicle->id, 'service_id' => $service->id, 'promo_code' => 'save10',
        'appointment_date' => today()->addDays(3)->toDateString(), 'appointment_time' => '10:00'];
    $this->actingAs($customer)->post(route('customer.appointments.store'), $payload)->assertRedirect(route('customer.appointments.index'))->assertSessionHasNoErrors();
    $reserved = Appointment::query()->sole();
    expect((float) $reserved->promotion_discount)->toBe(80.0);
    $payload['appointment_time'] = '12:00';
    $this->post(route('customer.appointments.store'), $payload)->assertSessionHasErrors('promo_code');
    $this->delete(route('customer.appointments.destroy', $reserved))->assertRedirect();
    $this->post(route('customer.appointments.store'), $payload)->assertRedirect(route('customer.appointments.index'))->assertSessionHasNoErrors();

    $booking = Appointment::query()->where('status', 'pending')->sole();
    $this->actingAs($shop->owner)->patch(route('shop.appointments.update', $booking), ['status' => 'confirmed'])->assertRedirect();
    $this->post(route('shop.repair-jobs.store'), ['appointment_id' => $booking->id, 'description' => 'Discounted repair'])->assertRedirect(route('shop.repair-jobs.index'));
    $job = RepairJob::query()->sole();
    $this->post(route('shop.quotations.store', $job), ['notes' => 'Apply booked discount'])->assertRedirect(route('shop.repair-jobs.index'))->assertSessionHasNoErrors();
    $estimate = Quotation::query()->sole();
    expect($estimate->total)->toBe('720.00');
    $this->actingAs($customer)->patch(route('customer.quotations.update', $estimate), ['status' => 'accepted'])->assertRedirect()->assertSessionHasNoErrors();
    $this->get(route('customer.repairs.index', ['repair_job_id' => $job->id]))
        ->assertOk()->assertSeeInOrder(['Work subtotal', '₱800.00', 'Discount', '−₱80.00', 'Approved estimate total', '₱720.00']);
    $job->update(['status' => 'completed']);
    $this->actingAs($shop->owner)->post(route('shop.invoices.store', $job))->assertRedirect(route('shop.invoices.index'))->assertSessionHasNoErrors();
    $bill = Invoice::query()->sole();
    expect($bill->subtotal)->toBe('800.00')->and($bill->discount)->toBe('80.00')->and($bill->total)->toBe('720.00');
    $this->actingAs(User::factory()->create(['role' => 'admin']))->patch(route('admin.promotions.disable', $promo))->assertRedirect();
    expect($promo->fresh()->disabled_at)->not->toBeNull();
});

it('shares only repair stages and selected sanitized photos, with expiry and revocation', function () {
    Storage::fake('local');
    [$shop, $customer, $vehicle] = extensionFixture();
    $job = RepairJob::query()->create(['job_number' => 'PRIVATE-TRACE',
        'shop_id' => $shop->id, 'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id,
        'status' => 'pending', 'description' => 'Private repair description', 'actual_cost' => 12345]);
    $job->statusHistories()->create(['to_status' => 'diagnosis', 'note' => 'Private staff note', 'changed_by' => $shop->owner_id, 'changed_at' => now()]);
    $photo = $job->photos()->create(['uploaded_by' => $shop->owner_id, 'stage' => 'damage', 'path' => "repair-evidence/{$job->id}/approved.png",
        'caption' => 'Private photo caption']);
    $otherPhoto = $job->photos()->create(['uploaded_by' => $shop->owner_id, 'stage' => 'before', 'path' => "repair-evidence/{$job->id}/hidden.png"]);
    $png = file_get_contents(public_path('icons/icon-192.png'));
    $privateChunk = "Comment\0Private embedded address";
    $png = substr($png, 0, 33).pack('N', strlen($privateChunk)).'tEXt'.$privateChunk.pack('N', crc32('tEXt'.$privateChunk)).substr($png, 33);
    Storage::disk('local')->put($photo->path, $png);

    $form = route('customer.repairs.shares.store', $job);
    $this->actingAs(User::factory()->create(['role' => 'customer']))->post($form, ['days' => 1])->assertNotFound();
    $this->actingAs($customer)->post($form, ['days' => 1, 'photo_ids' => [$photo->id]])->assertRedirect()->assertSessionHasNoErrors();
    $url = session('repair_share_url');
    $token = basename($url);
    $share = DB::table('repair_timeline_shares')->sole();
    expect($share->token_hash)->toBe(hash('sha256', $token))->not->toBe($token);
    Auth::forgetGuards();
    $this->get($url)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')->assertSee('Inspection')
        ->assertDontSee(['PRIVATE-TRACE', 'Private repair description', 'Private staff note', 'Private photo caption', $customer->name, $vehicle->plate_number, '12345']);
    $image = $this->get(route('repairs.shared.photo', [$token, $photo->id]))->assertOk()->assertHeader('Content-Type', 'image/png')->getContent();
    expect($image)->not->toContain('Private embedded address');
    $this->get(route('repairs.shared.photo', [$token, $otherPhoto->id]))->assertNotFound();
    $this->travel(2)->days();
    $this->get($url)->assertNotFound();
    $this->travelBack();
    $this->actingAs($customer)->delete(route('customer.repairs.shares.revoke', [$job, $share->id]))->assertRedirect();
    $this->get($url)->assertNotFound();
    $this->assertDatabaseHas('activity_logs', ['user_id' => $customer->id, 'action' => 'revoked_repair_share']);
});

function extensionFixture(): array
{
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $customer = User::factory()->create(['role' => 'customer']);
    $shop = Shop::factory()->create(['owner_id' => $owner->id, 'verification_status' => 'approved', 'shop_status' => 'active']);
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id]);

    return [$shop, $customer, $vehicle];
}
