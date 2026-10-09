<?php

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets an admin create rename and delete an unused category with an audit trail', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.service-categories.store'), ['name' => 'Diagnostics'])->assertRedirect();
    $category = ServiceCategory::query()->where('name', 'Diagnostics')->firstOrFail();
    $this->put(route('admin.service-categories.update', $category), ['name' => 'Engine diagnostics'])->assertRedirect();
    $this->get(route('admin.service-categories.index'))->assertSee('Engine diagnostics');
    $this->assertDatabaseHas('service_categories', ['id' => $category->id, 'name' => 'Engine diagnostics']);
    $this->delete(route('admin.service-categories.destroy', $category))->assertRedirect();

    $this->assertModelMissing($category);
    $this->assertDatabaseCount('activity_logs', 3);
    $this->assertDatabaseHas('activity_logs', ['user_id' => $admin->id, 'action' => 'deleted_service_category']);
});

it('preserves categories and services when deletion would cascade into catalog records', function () {
    $service = Service::factory()->create();

    $this->actingAs(User::factory()->create(['role' => 'admin']))->delete(route('admin.service-categories.destroy', $service->category_id))->assertSessionHasErrors('category');

    $this->assertModelExists($service);
    $this->assertDatabaseHas('service_categories', ['id' => $service->category_id]);
    $this->assertDatabaseCount('activity_logs', 0);
});

it('rejects duplicate or empty names without changing existing categories', function () {
    $category = ServiceCategory::factory()->create(['name' => 'Diagnostics']);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.service-categories.store'), ['name' => 'Diagnostics'])->assertSessionHasErrors('name');
    $this->post(route('admin.service-categories.store'), ['name' => ''])->assertSessionHasErrors('name');
    $this->put(route('admin.service-categories.update', $category), ['name' => 'Diagnostics'])->assertSessionHasNoErrors()->assertRedirect();

    $this->assertDatabaseCount('service_categories', 1);
});

it('denies category management to other roles', function (string $role) {
    $category = ServiceCategory::factory()->create(['name' => 'Diagnostics']);

    $this->actingAs(User::factory()->create(['role' => $role]))->get(route('admin.service-categories.index'))->assertForbidden();
    $this->post(route('admin.service-categories.store'), ['name' => 'Unauthorized'])->assertForbidden();
    $this->put(route('admin.service-categories.update', $category), ['name' => 'Unauthorized'])->assertForbidden();
    $this->delete(route('admin.service-categories.destroy', $category))->assertForbidden();

    $this->assertDatabaseHas('service_categories', ['id' => $category->id, 'name' => 'Diagnostics']);
    $this->assertDatabaseCount('activity_logs', 0);
})->with(['customer', 'shop_owner']);

it('escapes category names when rendering administration forms', function () {
    ServiceCategory::factory()->create(['name' => '<script>alert(1)</script>']);

    $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.service-categories.index'))
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
});
