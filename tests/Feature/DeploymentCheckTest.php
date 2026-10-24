<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('reports insecure production configuration without changing application data', function () {
    config(['app.url' => 'http://example.test', 'app.debug' => true, 'mail.default' => 'log', 'queue.default' => 'sync']);

    expect(Artisan::call('app:deployment-check', ['--strict' => true]))->toBe(1);
    expect(Artisan::output())
        ->toContain('[FAIL] Application URL uses HTTPS')
        ->toContain('[FAIL] Debug output is disabled')
        ->toContain('[FAIL] Mail transport is configured for delivery')
        ->toContain('[FAIL] Queue uses a worker-backed connection')
        ->toContain('backup and restore on disposable data');
});

it('reports a pending migration before deployment', function () {
    $migration = DB::table('migrations')->orderByDesc('migration')->value('migration');
    DB::table('migrations')->where('migration', $migration)->delete();

    expect(Artisan::call('app:deployment-check'))->toBe(1);
    expect(Artisan::output())
        ->toContain('[PASS] Database connection works')
        ->toContain('[FAIL] Database migrations are current');
});

it('refuses an unsupported backup database without touching application data', function () {
    expect(Artisan::call('app:backup'))->toBe(1);
    expect(Artisan::output())->toContain('supports MySQL and MariaDB only');
});
