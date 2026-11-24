<?php

use App\Models\Barangay;
use App\Models\RepairJob;
use App\Models\Review;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets an administrator remove a review for a violation and records the reason', function () {
    $fixture = moderationFixture();

    $this->actingAs($fixture['admin'])
        ->patch(route('admin.reviews.update', $fixture['review']), ['violation' => 'abuse', 'reason' => 'The review contains targeted abuse.'])
        ->assertRedirect();

    $this->assertDatabaseHas('reviews', ['id' => $fixture['review']->id, 'status' => 'removed', 'comment' => 'Good work.']);
    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $fixture['admin']->id,
        'action' => 'removed_review',
        'module' => 'review_moderation',
        'description' => "Removed review #{$fixture['review']->id} for Abuse or harassment. Reason: The review contains targeted abuse.",
    ]);
    $this->assertDatabaseHas('notifications', [
        'user_id' => $fixture['customer']->id,
        'type' => 'review_moderation',
    ]);
});

it('keeps public ratings in sync when a review is removed', function () {
    $fixture = moderationFixture();
    $shop = $fixture['review']->shop;
    $shop->refreshPublishedRating();
    expect($shop->fresh()->total_reviews)->toBe(1);

    $this->actingAs($fixture['admin'])->patch(route('admin.reviews.update', $fixture['review']), ['violation' => 'spam', 'reason' => 'Unrelated commercial solicitation.'])->assertRedirect();
    expect($shop->fresh()->total_reviews)->toBe(0);
    expect((float) $shop->fresh()->average_rating)->toBe(0.0);
    $this->get(route('shops.show', $shop))->assertOk()->assertDontSee('Good work.');
});

it('rejects the legacy hide action and requires an explanation for removal', function () {
    $fixture = moderationFixture();
    $url = route('admin.reviews.update', $fixture['review']);

    $this->actingAs($fixture['admin'])->patch($url, ['status' => 'hidden'])->assertInvalid(['status', 'violation', 'reason']);
    $this->patch($url, ['violation' => 'abuse', 'reason' => '  '])->assertInvalid('reason');

    expect($fixture['review']->fresh()->status)->toBe('published');
    $this->assertDatabaseMissing('activity_logs', ['action' => 'removed_review']);
});

it('shows moderation and audit pages only to administrators', function () {
    $fixture = moderationFixture();

    $this->actingAs($fixture['admin'])->get(route('admin.reviews.index'))->assertOk()->assertSee('Remove for a violation')->assertDontSee('Hide review');
    $this->actingAs($fixture['admin'])->get(route('admin.activity-logs.index'))->assertOk()->assertSee('Activity logs');
    $this->actingAs($fixture['customer'])->get(route('admin.reviews.index'))->assertForbidden();
    $this->actingAs($fixture['review']->shop->owner)
        ->patch(route('admin.reviews.update', $fixture['review']), ['violation' => 'abuse', 'reason' => 'Only administrators can remove reviews.'])
        ->assertForbidden();
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
