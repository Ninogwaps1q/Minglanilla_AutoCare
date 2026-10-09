<?php

use App\Models\Barangay;
use App\Models\Invoice;
use App\Models\RepairJob;
use App\Models\Service;
use App\Models\Shop;
use App\Models\ShopService;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

it('prevents duplicate records when simultaneous requests compete for the same workflow lock', function (string $operation) {
    $database = 'autocare_concurrency_test_'.bin2hex(random_bytes(8));
    $connection = config('database.connections.mysql');
    $connection['database'] = null;
    $connection['url'] = null;
    config(['database.connections.concurrency_admin' => $connection]);
    $admin = DB::connection('concurrency_admin');
    $admin->statement("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $workers = [];

    try {
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => $database, 'database.connections.mysql.url' => null]);
        DB::purge('mysql');
        Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
        $customer = User::factory()->create(['role' => 'customer']);
        $owner = User::factory()->create(['role' => 'shop_owner']);
        $shop = Shop::factory()->create([
            'owner_id' => $owner->id, 'barangay_id' => Barangay::factory()->create()->id,
            'name' => 'Concurrent booking test', 'phone' => '09170000000',
            'email' => 'concurrency@example.test', 'address' => 'Minglanilla',
            'verification_status' => 'approved', 'shop_status' => 'active',
        ]);
        $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id]);
        $user = $customer;
        $lock = $shop;
        if ($operation === 'booking') {
            $service = Service::factory()->create();
            ShopService::query()->create(['shop_id' => $shop->id, 'service_id' => $service->id, 'price' => 900, 'availability' => true]);
            $payload = ['shop_id' => $shop->id, 'vehicle_id' => $vehicle->id, 'service_id' => $service->id,
                'appointment_date' => today()->addDay()->toDateString(), 'appointment_time' => '10:00'];
            $path = route('customer.appointments.store', absolute: false);
        } elseif ($operation === 'invoice') {
            $lock = RepairJob::query()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id,
                'vehicle_id' => $vehicle->id, 'job_number' => 'JOB-CONCURRENT', 'description' => 'Brake inspection',
                'status' => 'completed', 'actual_cost' => 1000]);
            $payload = [];
            $user = $owner;
            $path = route('shop.invoices.store', $lock, absolute: false);
        } elseif ($operation === 'inventory') {
            $repairJob = RepairJob::query()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id,
                'vehicle_id' => $vehicle->id, 'job_number' => 'JOB-STOCK-CONCURRENT', 'description' => 'Brake inspection',
                'status' => 'repairing']);
            $lock = $shop->spareParts()->create(['name' => 'Brake pad', 'purchase_price' => 100,
                'selling_price' => 150, 'quantity' => 5, 'minimum_stock' => 2]);
            $payload = ['repair_job_id' => $repairJob->id, 'quantity' => 4];
            $user = $owner;
            $path = route('shop.inventory.consume', $lock, absolute: false);
        } else {
            $lock = Invoice::query()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id,
                'invoice_number' => 'INV-CONCURRENT', 'subtotal' => 1000, 'total' => 1000]);
            $payload = ['amount' => 1000, 'payment_method' => 'cash'];
            $path = route('customer.payments.store', $lock, absolute: false);
        }

        DB::beginTransaction();
        $lock->newQuery()->lockForUpdate()->findOrFail($lock->id);
        foreach ([1, 2] as $number) {
            $worker = new Process([PHP_BINARY, base_path('tests/Fixtures/concurrent-request.php'), $database, (string) $user->id, $path], base_path(), [
                'APP_ENV' => 'testing', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
            ]);
            $worker->setInput(json_encode($payload, JSON_THROW_ON_ERROR));
            $worker->setTimeout(30);
            $worker->start();
            $workers[] = $worker;
        }
        $deadline = microtime(true) + 20;
        $waiting = 0;
        do {
            $ids = array_map(fn (Process $worker): int => (int) strtok($worker->getOutput(), "\n"), $workers);
            if (min($ids) > 0) {
                $waiting = (int) $admin->selectOne("SELECT COUNT(*) AS waiting FROM information_schema.innodb_trx WHERE trx_mysql_thread_id IN (?, ?) AND trx_state = 'LOCK WAIT'", $ids)->waiting;
            }
            if ($waiting === 2) {
                break;
            }
            usleep(20000);
        } while (microtime(true) < $deadline);
        DB::commit();

        $statuses = [];
        foreach ($workers as $worker) {
            $worker->wait();
            expect($worker->getExitCode())->toBe(0);
            $lines = explode("\n", trim($worker->getOutput()));
            $statuses[] = json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR)['status'];
        }
        sort($statuses);
        $this->assertSame(2, $waiting, 'Workers: '.json_encode(array_map(fn (Process $worker): array => ['output' => $worker->getOutput(), 'error' => $worker->getErrorOutput()], $workers)));
        expect($statuses)->toBe($operation === 'invoice' ? [302, 302] : [302, 422]);
        if ($operation === 'booking') {
            $this->assertDatabaseCount('appointments', 1);
            $this->assertDatabaseHas('appointments', ['shop_id' => $shop->id, 'status' => 'pending']);
            $this->assertDatabaseCount('notifications', 1);
            $this->assertDatabaseHas('notifications', ['user_id' => $owner->id, 'type' => 'appointment_requested']);
        } elseif ($operation === 'invoice') {
            $this->assertDatabaseCount('invoices', 1);
            $this->assertDatabaseCount('invoice_items', 1);
            $this->assertDatabaseHas('invoices', ['repair_job_id' => $lock->id, 'total' => 1000]);
        } elseif ($operation === 'inventory') {
            $this->assertDatabaseHas('spare_parts', ['id' => $lock->id, 'quantity' => 1]);
            $this->assertDatabaseCount('inventory_transactions', 1);
            $this->assertDatabaseHas('inventory_transactions', [
                'spare_part_id' => $lock->id, 'type' => 'repair_consumption', 'quantity' => -4,
                'reference_type' => 'repair_job', 'reference_id' => $repairJob->id,
            ]);
        } else {
            $this->assertDatabaseCount('payments', 1);
            $this->assertDatabaseHas('payments', ['invoice_id' => $lock->id, 'amount' => 1000, 'status' => 'pending']);
            $this->assertDatabaseHas('invoices', ['id' => $lock->id, 'status' => 'unpaid']);
            $this->assertDatabaseCount('notifications', 1);
            $this->assertDatabaseHas('notifications', ['user_id' => $owner->id, 'type' => 'payment_submitted']);
        }
    } finally {
        if (DB::connection('mysql')->transactionLevel() > 0) {
            DB::connection('mysql')->rollBack();
        }
        foreach ($workers as $worker) {
            $worker->stop();
        }
        DB::purge('mysql');
        $admin->statement("DROP DATABASE `{$database}`");
        DB::purge('concurrency_admin');
    }
})->with(['booking', 'invoice', 'payment', 'inventory'])->skip(fn (): bool => getenv('RUN_MYSQL_CONCURRENCY') !== '1', 'Requires an isolated database and permission to inspect InnoDB transactions.');
