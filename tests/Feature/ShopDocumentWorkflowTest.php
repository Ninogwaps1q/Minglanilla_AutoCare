<?php

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('lets a shop owner upload a private document and an admin review it', function () {
    Storage::fake('local');
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $shop = Shop::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($owner)->post(route('shop.documents.store'), ['document_type' => 'business_permit', 'expires_at' => today()->addYear()->toDateString(), 'document' => UploadedFile::fake()->create('permit.pdf', 120, 'application/pdf')])->assertRedirect();
    $document = $shop->documents()->sole();
    Storage::disk('local')->assertExists($document->document_path);

    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get(route('admin.shop-documents.download', $document))->assertDownload();
    $this->patch(route('admin.shop-documents.update', $document), ['verification_status' => 'verified'])->assertRedirect();
    $document->refresh();
    expect($document->verification_status)->toBe('verified')
        ->and($document->reviewed_by)->toBe($admin->id)
        ->and($document->reviewed_at)->not->toBeNull();
});

it('blocks another shop owner from reading or deleting a private document', function () {
    Storage::fake('local');
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $document = Shop::factory()->create(['owner_id' => $owner->id])->documents()->create(['document_type' => 'business_permit', 'document_path' => 'shop-documents/permit.pdf']);
    $otherOwner = User::factory()->create(['role' => 'shop_owner']);

    $this->actingAs($otherOwner)->get(route('shop.documents.download', $document))->assertNotFound();
    $this->delete(route('shop.documents.destroy', $document))->assertNotFound();
});

it('rejects executable and oversized verification uploads', function () {
    $owner = User::factory()->create(['role' => 'shop_owner']);
    Shop::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($owner)->post(route('shop.documents.store'), ['document_type' => 'business_permit', 'document' => UploadedFile::fake()->create('permit.svg', 10, 'image/svg+xml')])->assertSessionHasErrors('document');
    $this->post(route('shop.documents.store'), ['document_type' => 'business_permit', 'document' => UploadedFile::fake()->create('permit.pdf', 6000, 'application/pdf')])->assertSessionHasErrors('document');
    $this->post(route('shop.documents.store'), ['document_type' => 'business_permit', 'expires_at' => today()->subDay()->toDateString(), 'document' => UploadedFile::fake()->create('permit.pdf', 10, 'application/pdf')])->assertSessionHasErrors('expires_at');
});
