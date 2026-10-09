<?php

use App\Models\Barangay;
use App\Models\MessageThread;
use App\Models\RepairJob;
use App\Models\Shop;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets a customer create a maintenance reminder for their vehicle', function () {
    $fixture = engagementFixture();

    $this->actingAs($fixture['customer'])->post(route('customer.reminders.store'), [
        'vehicle_id' => $fixture['vehicle']->id,
        'title' => 'Oil change',
        'message' => 'Replace engine oil.',
        'reminder_date' => now()->addMonth()->toDateString(),
        'type' => 'date',
    ])->assertRedirect();

    $this->assertDatabaseHas('maintenance_reminders', ['vehicle_id' => $fixture['vehicle']->id, 'title' => 'Oil change']);
    $this->assertDatabaseHas('notifications', ['user_id' => $fixture['customer']->id, 'type' => 'maintenance_reminder']);
});

it('lets a customer review a completed repair job and notifies the shop owner', function () {
    $fixture = engagementFixture();
    $fixture['repairJob']->update(['status' => 'completed']);

    $this->actingAs($fixture['customer'])->post(route('customer.reviews.store'), [
        'repair_job_id' => $fixture['repairJob']->id,
        'rating' => 5,
        'comment' => 'Excellent service.',
    ])->assertRedirect();

    $this->assertDatabaseHas('reviews', ['repair_job_id' => $fixture['repairJob']->id, 'rating' => 5]);
    $this->assertDatabaseHas('shops', ['id' => $fixture['shop']->id, 'average_rating' => 5, 'total_reviews' => 1]);
    $this->assertDatabaseHas('notifications', ['user_id' => $fixture['owner']->id, 'type' => 'review_received']);

    $review = $fixture['repairJob']->review()->firstOrFail();

    $this->actingAs($fixture['owner'])
        ->patch(route('shop.reviews.update', $review), ['response' => 'Thank you for visiting us.'])
        ->assertRedirect();

    $this->assertDatabaseHas('reviews', ['id' => $review->id, 'response' => 'Thank you for visiting us.']);
    $this->assertDatabaseHas('notifications', ['user_id' => $fixture['customer']->id, 'type' => 'review_response']);
});

it('exchanges messages between a customer and a shop owner with notifications', function () {
    $fixture = engagementFixture();

    $this->actingAs($fixture['customer'])
        ->get(route('customer.messages.show', $fixture['shop']))
        ->assertOk();

    $thread = MessageThread::query()->firstOrFail();

    $this->actingAs($fixture['customer'])
        ->post(route('customer.messages.store', $thread), ['message' => 'Can you inspect my brakes?'])
        ->assertRedirect();

    $this->assertDatabaseHas('messages', ['thread_id' => $thread->id, 'sender_id' => $fixture['customer']->id]);
    $this->assertDatabaseHas('notifications', ['user_id' => $fixture['owner']->id, 'type' => 'message_received']);

    $this->actingAs($fixture['owner'])
        ->post(route('shop.messages.store', $thread), ['message' => 'Yes, please book an appointment.'])
        ->assertRedirect();

    $this->assertDatabaseHas('messages', ['thread_id' => $thread->id, 'sender_id' => $fixture['owner']->id]);
    $this->assertDatabaseHas('notifications', ['user_id' => $fixture['customer']->id, 'type' => 'message_received']);
});

/**
 * @return array{customer: User, owner: User, shop: Shop, vehicle: Vehicle, repairJob: RepairJob}
 */
function engagementFixture(): array
{
    $barangay = Barangay::factory()->create();
    $customer = User::factory()->create(['role' => 'customer']);
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $shop = Shop::query()->create([
        'owner_id' => $owner->id,
        'name' => 'Engagement Shop',
        'phone' => '09170000000',
        'email' => 'engagement@example.test',
        'address' => 'Demo Street',
        'barangay_id' => $barangay->id,
        'verification_status' => 'approved',
        'shop_status' => 'active',
    ]);
    $vehicle = $customer->vehicles()->create(['plate_number' => 'ENGAGE-001', 'make' => 'Toyota', 'model' => 'Vios', 'year' => 2020]);
    $repairJob = RepairJob::query()->create([
        'customer_id' => $customer->id,
        'shop_id' => $shop->id,
        'vehicle_id' => $vehicle->id,
        'job_number' => 'RJ-ENGAGE-001',
        'description' => 'Brake inspection',
        'status' => 'pending',
    ]);

    return compact('customer', 'owner', 'shop', 'vehicle', 'repairJob');
}
