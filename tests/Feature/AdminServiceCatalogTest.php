<?php

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders Bootstrap pagination for a catalog with multiple pages', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    Service::factory()->count(20)->create();

    $this->actingAs($admin)->get('/admin/services')->assertOk()
        ->assertSee('class="pagination"', false)
        ->assertDontSee('<svg', false);
});

test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

it('redirects guests from the service catalog to login', function () {
    $this->get('/admin/services')->assertRedirect('/login');
    $this->post('/admin/services', [])->assertRedirect('/login');
});

it('prevents non-admin users from reading or changing the catalog', function (string $role) {
    $user = User::factory()->create(['role' => $role]);
    $service = Service::factory()->create();

    $this->actingAs($user)->get('/admin/services')->assertForbidden();
    $this->post('/admin/services', [])->assertForbidden();
    $this->get("/admin/services/{$service->id}/edit")->assertForbidden();
    $this->put("/admin/services/{$service->id}", ['name' => 'Changed'])->assertForbidden();
    expect($service->fresh()->name)->toBe($service->name);
})->with(['customer', 'shop_owner']);

it('creates a catalog service and records the admin action', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = ServiceCategory::factory()->create();
    $payload = ['category_id' => $category->id, 'name' => 'Brake inspection', 'description' => 'Inspect brake wear.', 'default_price' => '350.50', 'estimated_duration' => 30];

    $this->actingAs($admin)->post('/admin/services', $payload)
        ->assertRedirect(route('admin.services.index'))->assertSessionHas('status', 'Service created.');

    $this->assertDatabaseHas('services', $payload);
    $this->assertDatabaseHas('activity_logs', ['user_id' => $admin->id, 'action' => 'created_service', 'module' => 'service_catalog']);
});

it('updates an existing service and allows optional estimates to be cleared', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $service = Service::factory()->create(['default_price' => 500, 'estimated_duration' => 60]);
    $category = ServiceCategory::factory()->create();

    $this->actingAs($admin)->get("/admin/services/{$service->id}/edit")->assertOk()->assertSee($service->name);
    $this->put("/admin/services/{$service->id}", [
        'category_id' => $category->id, 'name' => 'Updated inspection', 'description' => null,
        'default_price' => null, 'estimated_duration' => null,
    ])->assertRedirect(route('admin.services.index'))->assertSessionHas('status', 'Service updated.');

    $this->assertDatabaseHas('services', ['id' => $service->id, 'category_id' => $category->id, 'name' => 'Updated inspection', 'default_price' => null, 'estimated_duration' => null]);
    $this->assertDatabaseHas('activity_logs', ['user_id' => $admin->id, 'action' => 'updated_service']);
});

it('rejects invalid catalog values without saving or logging a change', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post('/admin/services', [
        'category_id' => 999999, 'name' => '', 'default_price' => -1, 'estimated_duration' => 65536,
    ])->assertSessionHasErrors(['category_id', 'name', 'default_price', 'estimated_duration']);

    $this->assertDatabaseCount('services', 0);
    $this->assertDatabaseCount('activity_logs', 0);
});

it('filters the catalog by name and category and escapes service names', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = ServiceCategory::factory()->create();
    $service = Service::factory()->create(['category_id' => $category->id, 'name' => '<script>alert(1)</script> Brake']);
    $other = Service::factory()->create(['name' => 'Oil replacement']);

    $this->actingAs($admin)->get('/admin/services?'.http_build_query(['search' => 'Brake', 'category_id' => $category->id]))
        ->assertOk()->assertSee($service->name)->assertDontSee($service->name, false)->assertDontSee($other->name);
});

it('shows validation feedback and keeps the entered service name after a failed save', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = ServiceCategory::factory()->create();

    $this->actingAs($admin)->from(route('admin.services.index'))->post('/admin/services', [
        'category_id' => $category->id,
        'name' => 'Brake inspection',
        'default_price' => -1,
    ])->assertRedirect(route('admin.services.index'))->assertSessionHasErrors('default_price');

    $this->get(route('admin.services.index'))->assertOk()
        ->assertSee('Service could not be saved. Please correct these fields:')
        ->assertSee('The default price field must be at least 0.')
        ->assertSee('value="Brake inspection"', false);
    $this->assertDatabaseCount('services', 0);
});
