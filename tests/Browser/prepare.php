<?php

use App\Models\Barangay;
use App\Models\Invoice;
use App\Models\RepairJob;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Shop;
use App\Models\ShopService;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('testing') || $app->configurationIsCached()) {
    throw new RuntimeException('Browser fixtures require APP_ENV=testing and uncached configuration.');
}

$directory = storage_path('framework/testing');
if (! is_dir($directory)) {
    mkdir($directory, 0700, true);
}
$database = tempnam($directory, 'browser-');
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $database, 'database.connections.sqlite.url' => null]);
DB::purge('sqlite');
if (Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]) !== 0) {
    throw new RuntimeException('Could not migrate the isolated browser database.');
}

$password = bin2hex(random_bytes(16));
foreach (['customer', 'shop_owner', 'admin'] as $role) {
    $users[$role] = User::factory()->create([
        'role' => $role, 'email' => $role.'@browser.example.test', 'password' => $password,
        'name' => 'Browser '.str_replace('_', ' ', $role),
    ]);
}
$barangay = Barangay::factory()->create();
$shop = Shop::factory()->create([
    'owner_id' => $users['shop_owner']->id, 'barangay_id' => $barangay->id,
    'name' => 'Browser Test AutoCare', 'phone' => '09170000000',
    'email' => 'shop@browser.example.test', 'address' => 'Minglanilla',
    'gcash_number' => '09171234567', 'bank_transfer_details' => "Test Bank\nAccount: Browser Test AutoCare\nNo. 123456",
    'latitude' => 10.2443, 'longitude' => 123.7898,
    'verification_status' => 'approved', 'shop_status' => 'active',
    'opening_time' => '08:00', 'closing_time' => '17:00',
]);
$browserDocumentIds = [];
foreach (['desktop', 'mobile', 'small-mobile'] as $viewport) {
    $browserDocumentIds[$viewport] = $shop->documents()->create([
        'document_type' => 'business_permit',
        'document_path' => "shop-documents/browser-{$viewport}.pdf",
        'verification_status' => 'pending',
    ])->id;
}
$vehicle = Vehicle::factory()->create(['customer_id' => $users['customer']->id, 'plate_number' => 'BROWSER-001']);
$category = ServiceCategory::factory()->create();
$service = Service::factory()->create(['category_id' => $category->id, 'name' => 'Browser Oil Change']);
ShopService::query()->create(['shop_id' => $shop->id, 'service_id' => $service->id, 'price' => 900, 'availability' => true]);
$repairJob = RepairJob::query()->create([
    'customer_id' => $users['customer']->id, 'shop_id' => $shop->id, 'vehicle_id' => $vehicle->id,
    'job_number' => 'JOB-BROWSER-STOCK', 'status' => 'repairing', 'description' => 'Brake maintenance',
]);
$diagnosisJob = RepairJob::query()->create([
    'customer_id' => $users['customer']->id, 'shop_id' => $shop->id, 'vehicle_id' => $vehicle->id,
    'job_number' => 'JOB-BROWSER-DIAGNOSIS', 'status' => 'diagnosis', 'description' => 'Brake diagnosis', 'actual_cost' => 1250,
]);
$diagnosisJob->items()->createMany([
    ['type' => 'labor', 'description' => 'Brake labor', 'quantity' => 1, 'unit_price' => 500, 'subtotal' => 500],
    ['type' => 'part', 'description' => 'Brake pads', 'quantity' => 1, 'unit_price' => 750, 'subtotal' => 750],
]);
$part = $shop->spareParts()->create(['name' => 'Browser Brake Pad', 'purchase_price' => 100,
    'selling_price' => 150, 'quantity' => 10, 'minimum_stock' => 2]);
$invoice = Invoice::query()->create([
    'customer_id' => $users['customer']->id, 'shop_id' => $shop->id,
    'invoice_number' => 'INV-BROWSER-001', 'subtotal' => 500, 'total' => 500,
]);
$invoice->items()->create(['description' => 'Browser brake service', 'quantity' => 2,
    'unit_price' => 250, 'subtotal' => 500]);
echo json_encode(['database' => $database, 'password' => $password, 'shop' => $shop->id,
    'vehicle' => $vehicle->id, 'service' => $service->id, 'repairJob' => $repairJob->id, 'diagnosisRepairJob' => $diagnosisJob->id,
    'part' => $part->id, 'invoice' => $invoice->id, 'browserDocuments' => $browserDocumentIds], JSON_THROW_ON_ERROR);
