<?php

use App\Models\Appointment;
use App\Models\Review;
use App\Models\ReviewReport;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets a provider report a published review once and records admin decisions', function () {
    $shop = Shop::factory()->create();
    $admin = User::factory()->create(['role' => 'admin']);
    $review = Review::query()->create(['shop_id' => $shop->id, 'customer_id' => User::factory()->create()->id, 'rating' => 1, 'comment' => 'Reported review', 'status' => 'published']);
    $route = route('reviews.report', $review);

    $this->actingAs($shop->owner)->post($route)->assertSessionHasErrors('reason');
    $this->post($route, ['reason' => 'Possible abuse'])->assertRedirect();
    $this->post($route, ['reason' => 'Duplicate'])->assertRedirect();
    $this->assertDatabaseCount('review_reports', 1);
    expect($review->fresh()->status)->toBe('published');

    $report = ReviewReport::query()->firstOrFail();
    $this->actingAs($admin)->get(route('admin.reviews.index'))->assertOk()->assertSee('Possible abuse');
    $this->patch(route('admin.review-reports.dismiss', $report))->assertSessionHasErrors('reason');
    $this->patch(route('admin.review-reports.dismiss', $report), ['reason' => 'The review describes the service'])->assertRedirect();
    $this->assertDatabaseHas('review_reports', ['id' => $report->id, 'status' => 'dismissed', 'decided_by' => $admin->id]);
    $this->assertDatabaseHas('activity_logs', ['action' => 'dismissed_review_report', 'user_id' => $admin->id]);
    expect($review->fresh()->status)->toBe('published');

    $reporter = User::factory()->create();
    $this->actingAs($reporter)->post($route, ['reason' => 'It includes private information'])->assertRedirect();
    $this->actingAs($admin)->patch(route('admin.reviews.update', $review), [
        'violation' => 'private_information', 'reason' => 'It exposes private information',
    ])->assertRedirect();
    $this->assertDatabaseHas('review_reports', ['review_id' => $review->id, 'reporter_id' => $reporter->id, 'status' => 'removed']);
});

it('rate limits review submissions per account', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $vehicle = $customer->vehicles()->create(['plate_number' => 'ABC 1234', 'make' => 'Toyota', 'model' => 'Vios', 'year' => 2020]);
    $shop = Shop::factory()->create();

    $this->actingAs($customer);
    foreach (range(1, 6) as $index) {
        $booking = Appointment::query()->create([
            'customer_id' => $customer->id, 'shop_id' => $shop->id, 'vehicle_id' => $vehicle->id,
            'appointment_date' => today()->subDays($index), 'appointment_time' => '09:00', 'status' => 'completed',
        ]);
        $this->post(route('customer.reviews.store'), ['appointment_id' => $booking->id, 'rating' => 5])
            ->assertStatus($index === 6 ? 429 : 302);
    }

    $this->assertDatabaseCount('reviews', 5);
});
