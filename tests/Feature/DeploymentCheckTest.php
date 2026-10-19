<?php

use Illuminate\Support\Facades\Artisan;

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
