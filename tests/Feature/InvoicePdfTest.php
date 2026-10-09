<?php

use App\Models\Barangay;
use App\Models\Invoice;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('downloads a real private PDF for the invoice customer and shop owner', function () {
    [$invoice, $customer, $owner] = invoicePdfFixture();

    foreach ([[$customer, 'customer.invoices.pdf'], [$owner, 'shop.invoices.pdf']] as [$user, $route]) {
        $response = $this->actingAs($user)->get(route($route, $invoice));

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        expect($response->headers->get('Cache-Control'))->toContain('private')->toContain('no-store');
        expect($response->headers->get('Content-Disposition'))->toContain('attachment')->toContain("invoice-{$invoice->id}.pdf");
        expect(str_starts_with($response->getContent(), '%PDF-'))->toBeTrue();
    }

    $this->actingAs($customer)->get(route('customer.invoices.show', $invoice))->assertSee('Download PDF');
    $this->actingAs($owner)->get(route('shop.invoices.show', $invoice))->assertSee('Download PDF');
});

it('does not expose invoice PDFs to another customer or shop owner', function () {
    [$invoice] = invoicePdfFixture();
    $otherCustomer = User::factory()->create(['role' => 'customer']);
    $otherOwner = User::factory()->create(['role' => 'shop_owner']);
    Shop::factory()->create(['owner_id' => $otherOwner->id, 'barangay_id' => Barangay::factory()->create()->id,
        'name' => 'Other PDF shop', 'phone' => '09170000001', 'email' => 'other-pdf@example.test', 'address' => 'Minglanilla']);

    $this->actingAs($otherCustomer)->get(route('customer.invoices.pdf', $invoice))->assertNotFound();
    $this->actingAs($otherOwner)->get(route('shop.invoices.pdf', $invoice))->assertNotFound();
    $this->get(route('customer.invoices.pdf', $invoice))->assertForbidden();
});

/** @return array{Invoice, User, User} */
function invoicePdfFixture(): array
{
    $customer = User::factory()->create(['role' => 'customer']);
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $shop = Shop::factory()->create(['owner_id' => $owner->id, 'barangay_id' => Barangay::factory()->create()->id,
        'name' => 'PDF Service Shop', 'phone' => '09170000000', 'email' => 'pdf@example.test', 'address' => 'Minglanilla']);
    $invoice = Invoice::query()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id,
        'invoice_number' => 'INV-PDF-001', 'subtotal' => 500, 'total' => 500]);
    $invoice->items()->create(['description' => 'Brake service', 'quantity' => 2, 'unit_price' => 250, 'subtotal' => 500]);

    return [$invoice, $customer, $owner];
}
