<?php

use App\Models\RepairJob;
use App\Models\ServiceCase;
use App\Models\Shop;
use App\Models\User;
use App\Models\VehicleServiceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('stores private before and after repair photos and refuses unrelated users', function () {
    Storage::fake('local');
    [$customer, $owner, $shop, $job] = aftercareFixture();
    $otherCustomer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($owner)->post(route('shop.repair-photos.store', $job), [
        'stage' => 'after', 'photo' => aftercareImage('after.png'),
    ])->assertSessionHasErrors('stage');
    $this->assertDatabaseCount('repair_photos', 0);

    $this->post(route('shop.repair-photos.store', $job), [
        'stage' => 'before', 'photo' => aftercareImage('before.png'), 'caption' => 'Damaged bumper',
    ])->assertRedirect();
    $photo = $job->photos()->sole();
    Storage::disk('local')->assertExists($photo->path);
    $this->actingAs($customer)->get(route('repair-photos.show', $photo))->assertOk();
    $this->actingAs($otherCustomer)->get(route('repair-photos.show', $photo))->assertNotFound();

    $job->update(['status' => 'repairing', 'started_at' => now()]);
    $this->actingAs($owner)->post(route('shop.repair-photos.store', $job), [
        'stage' => 'before', 'photo' => aftercareImage('late-before.png'),
    ])->assertSessionHasErrors('stage');
    $this->actingAs($owner)->post(route('shop.repair-photos.store', $job), [
        'stage' => 'after', 'photo' => aftercareImage('after.png'),
    ])->assertRedirect();
    $this->assertDatabaseCount('repair_photos', 2);
    $this->actingAs($customer)->get(route('customer.repairs.index'))->assertSee('Before & after photos', false);
});

it('issues an explicit warranty and tracks a customer follow-up through the shop', function () {
    [$customer, $owner, $shop, $job] = aftercareFixture();
    $job->update(['status' => 'completed', 'started_at' => now()->subDay(), 'completed_at' => now()]);
    VehicleServiceRecord::query()->create([
        'repair_job_id' => $job->id, 'vehicle_id' => $job->vehicle_id,
        'shop_id' => $shop->id, 'customer_id' => $customer->id,
        'service_date' => today(), 'service_type' => 'Brake repair',
        'total_cost' => 1500,
    ]);

    $this->actingAs($owner)->post(route('shop.repair-warranties.store', $job), [
        'coverage' => 'Brake pads and labor on this repair only', 'duration_days' => 90,
    ])->assertRedirect();
    $this->assertDatabaseHas('repair_warranties', ['repair_job_id' => $job->id, 'coverage' => 'Brake pads and labor on this repair only']);
    $this->post(route('shop.repair-warranties.store', $job), [
        'coverage' => 'Changed coverage', 'duration_days' => 60,
    ])->assertSessionHasErrors('coverage');

    $this->actingAs($customer)->post(route('customer.service-cases.store', $job), [
        'type' => 'warranty_claim', 'subject' => 'Brake noise returned',
        'description' => 'The brake noise returned three days after the repair.',
    ])->assertRedirect(route('customer.service-cases.index'));
    $case = ServiceCase::query()->sole();
    $this->post(route('customer.service-cases.store', $job), [
        'type' => 'warranty_claim', 'subject' => 'Duplicate request',
        'description' => 'This should not make a second open case.',
    ])->assertSessionHasErrors('type');

    $this->actingAs($owner)->patch(route('shop.service-cases.update', $case), [
        'status' => 'scheduled', 'shop_response' => 'Please bring the vehicle on Monday for a free inspection.',
    ])->assertRedirect();
    $this->assertDatabaseHas('service_cases', ['id' => $case->id, 'status' => 'scheduled']);
    $this->get(route('shop.service-cases.index'))->assertSee('Brake noise returned');
    $this->get(route('shop.today-work.index'))->assertSee('Brake noise returned');
    $this->actingAs($customer)->get(route('customer.service-cases.index'))->assertSee('Please bring the vehicle on Monday');
    $this->get(route('customer.services.index'))
        ->assertSee('Warranty active')
        ->assertSee('repair_job_id='.$job->id, false);
    $this->get(route('customer.repairs.index', ['repair_job_id' => $job->id]))
        ->assertViewHas('repairJobs', fn ($jobs): bool => $jobs->total() === 1);
});

it('lets only admins resolve complaints and records the decision history', function () {
    [$customer, $owner, $shop, $job] = aftercareFixture();
    $job->update(['status' => 'completed', 'started_at' => now()->subDay(), 'completed_at' => now()]);
    $admin = User::factory()->create(['role' => 'admin']);
    $otherOwner = User::factory()->create(['role' => 'shop_owner']);
    Shop::factory()->create(['owner_id' => $otherOwner->id]);

    $this->actingAs($customer)->post(route('customer.service-cases.store', $job), [
        'type' => 'complaint', 'subject' => 'Unresolved repair',
        'description' => 'The original problem is still present after the repair.',
    ])->assertRedirect(route('customer.service-cases.index'));
    $case = ServiceCase::query()->sole();

    $this->actingAs($otherOwner)->patch(route('shop.service-cases.update', $case), [
        'status' => 'resolved', 'shop_response' => 'Incorrect shop',
    ])->assertNotFound();
    $this->actingAs($owner)->patch(route('shop.service-cases.update', $case), [
        'status' => 'resolved', 'shop_response' => 'We reviewed the job notes.',
    ])->assertSessionHasErrors('status');
    $this->actingAs($admin)->patch(route('admin.service-cases.update', $case), [
        'status' => 'resolved',
    ])->assertSessionHasErrors('admin_resolution');
    $this->get(route('admin.service-cases.index'))->assertSee('Unresolved repair')->assertSee('1 need review');
    $this->patch(route('admin.service-cases.update', $case), [
        'status' => 'resolved', 'admin_resolution' => 'The shop will inspect the vehicle again without a diagnostic fee.',
    ])->assertRedirect();

    $this->assertDatabaseHas('service_cases', ['id' => $case->id, 'status' => 'resolved', 'resolved_by' => $admin->id]);
    $this->assertDatabaseHas('service_case_events', ['service_case_id' => $case->id, 'to_status' => 'resolved', 'actor_id' => $admin->id]);
    $this->actingAs($customer)->get(route('customer.service-cases.index'))->assertSee('The shop will inspect the vehicle again');
});

/** @return array{User, User, Shop, RepairJob} */
function aftercareFixture(): array
{
    $customer = User::factory()->create(['role' => 'customer']);
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $shop = Shop::factory()->create(['owner_id' => $owner->id]);
    $vehicle = $customer->vehicles()->create(['plate_number' => 'AFTER-001', 'make' => 'Toyota', 'model' => 'Vios', 'year' => 2020]);
    $job = RepairJob::factory()->forVehicle($vehicle)->create(['shop_id' => $shop->id, 'job_number' => 'RJ-AFTER-001']);

    return [$customer, $owner, $shop, $job];
}

function aftercareImage(string $name): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9JQqkAAAAASUVORK5CYII='));
}
