<?php

use App\Models\Barangay;
use App\Models\RepairJob;
use App\Models\Review;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets an administrator hide a review and records the moderation action', function () {
    $fixture = moderationFixture();

    $this->actingAs($fixture['admin'])
        ->patch(route('admin.reviews.update', $fixture['review']), ['status' => 'hidden'])
        ->assertRedirect();

    $this->assertDatabaseHas('reviews', ['id' => $fixture['review']->id, 'status' => 'hidden']);
    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $fixture['admin']->id,
        'action' => 'updated_review_status',
        'module' => 'review_moderation',
    ]);
    $this->assertDatabaseHas('notifications', [
        'user_id' => $fixture['customer']->id,
        'type' => 'review_moderation',
    ]);
});

it('keeps public ratings in sync when an administrator hides and restores reviews', function () {
    $fixture = moderationFixture();
    $shop = $fixture['review']->shop;
    $shop->refreshPublishedRating();
    expect($shop->fresh()->total_reviews)->toBe(1);

    $this->actingAs($fixture['admin'])->patch(route('admin.reviews.update', $fixture['review']), ['status' => 'hidden'])->assertRedirect();
    expect($shop->fresh()->total_reviews)->toBe(0);
    expect((float) $shop->fresh()->average_rating)->toBe(0.0);

    $this->patch(route('admin.reviews.update', $fixture['review']), ['status' => 'published'])->assertRedirect();
    expect($shop->fresh()->total_reviews)->toBe(1);
    expect((float) $shop->fresh()->average_rating)->toBe(4.0);
});

it('shows moderation and audit pages only to administrators', function () {
    $fixture = moderationFixture();

    $this->actingAs($fixture['admin'])->get(route('admin.reviews.index'))->assertOk()->assertSee('Review moderation');
    $this->actingAs($fixture['admin'])->get(route('admin.activity-logs.index'))->assertOk()->assertSee('Activity logs');
    $this->actingAs($fixture['customer'])->get(route('admin.reviews.index'))->assertForbidden();
});

/**
 * @return array{admin: User, customer: User, review: Review}
 */
function moderationFixture(): array
{
    $barangay = Barangay::factory()->create();
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = User::factory()->create(['role' => 'customer']);
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $shop = Shop::query()->create([
        'owner_id' => $owner->id,
        'name' => 'Moderation Shop',
        'phone' => '09170000000',
        'email' => 'moderation@example.test',
        'address' => 'Demo Street',
        'barangay_id' => $barangay->id,
        'verification_status' => 'approved',
        'shop_status' => 'active',
    ]);
    $vehicle = $customer->vehicles()->create(['plate_number' => 'MOD-001', 'make' => 'Toyota', 'model' => 'Vios', 'year' => 2020]);
    $repairJob = RepairJob::query()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'vehicle_id' => $vehicle->id, 'job_number' => 'RJ-MOD-001', 'description' => 'Moderation test', 'status' => 'completed']);
    $review = Review::query()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'repair_job_id' => $repairJob->id, 'rating' => 4, 'comment' => 'Good work.', 'status' => 'published']);

    return compact('admin', 'customer', 'review');
}
