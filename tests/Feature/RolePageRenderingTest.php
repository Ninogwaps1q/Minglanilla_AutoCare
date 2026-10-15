<?php

use App\Models\Barangay;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders customer workspace pages without records', function (string $route) {
    $this->actingAs(User::factory()->create(['role' => 'customer']))->get(route($route))->assertOk();
})->with([
    'dashboard', 'customer.vehicles.index', 'customer.vehicles.create', 'customer.profile.edit',
    'customer.appointments.index', 'customer.appointments.create', 'customer.quotations.index',
    'customer.quotations.create', 'customer.repairs.index', 'customer.inspections.index',
    'customer.invoices.index', 'customer.reminders.index', 'customer.messages.index',
    'customer.reviews.index', 'customer.favorites.index', 'customer.service-history.index',
    'customer.notifications.index', 'customer.emergency-requests.index', 'customer.ai-assistant.index',
]);

it('renders shop workspace pages for an approved shop', function (string $route) {
    $owner = User::factory()->create(['role' => 'shop_owner']);
    Shop::factory()->create([
        'owner_id' => $owner->id, 'barangay_id' => Barangay::factory()->create()->id,
        'name' => 'Page audit shop', 'phone' => '09170000000', 'email' => 'audit@example.test',
        'address' => 'Minglanilla', 'verification_status' => 'approved', 'shop_status' => 'active',
    ]);

    $this->actingAs($owner)->get(route($route))->assertOk();
})->with([
    'shop.dashboard', 'shop.profile.edit', 'shop.services.index', 'shop.appointments.index',
    'shop.repair-jobs.index', 'shop.repair-jobs.create', 'shop.quotations.index',
    'shop.invoices.index', 'shop.inventory.index', 'shop.technicians.index',
    'shop.messages.index', 'shop.reviews.index', 'shop.notifications.index',
    'shop.reports.index', 'shop.emergency-requests.index',
]);

it('renders administration pages without operational records', function (string $route) {
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route($route))->assertOk();
})->with([
    'admin.dashboard', 'admin.users.index', 'admin.shops.index', 'admin.services.index',
    'barangays.index', 'admin.reviews.index', 'admin.reports.index', 'admin.activity-logs.index',
]);

it('shows the actual page name in the top bar for shop pages outside the sidebar', function () {
    $owner = User::factory()->create(['role' => 'shop_owner']);
    Shop::factory()->create([
        'owner_id' => $owner->id,
        'barangay_id' => Barangay::factory()->create()->id,
        'verification_status' => 'approved',
        'shop_status' => 'active',
    ]);

    $this->actingAs($owner)->get(route('shop.notifications.index'))
        ->assertOk()
        ->assertSee('<strong>Notifications</strong>', false);
});
