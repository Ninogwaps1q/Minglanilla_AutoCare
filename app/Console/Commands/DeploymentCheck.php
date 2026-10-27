<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Throwable;

class DeploymentCheck extends Command
{
    protected $signature = 'app:deployment-check {--strict : Require production-safe configuration even outside production}';

    protected $description = 'Check deployment configuration and local storage without changing data.';

    public function handle(): int
    {
        $strict = app()->environment('production') || (bool) $this->option('strict');
        $databaseAvailable = true;
        try {
            DB::connection()->getPdo();
        } catch (Throwable) {
            $databaseAvailable = false;
        }

        $migrationsCurrent = false;
        if ($databaseAvailable) {
            try {
                $migrator = app(Migrator::class);
                if ($migrator->repositoryExists()) {
                    $migrationFiles = $migrator->getMigrationFiles(array_merge($migrator->paths(), [database_path('migrations')]));
                    $migrationsCurrent = count(array_diff(array_keys($migrationFiles), $migrator->getRepository()->getRan())) === 0;
                }
            } catch (Throwable) {
                $migrationsCurrent = false;
            }
        }

        $publicDiskIsLocal = config('filesystems.disks.public.driver') === 'local';
        $publicStorageLinked = ! $publicDiskIsLocal || (
            realpath(public_path('storage')) !== false
            && realpath(public_path('storage')) === realpath(storage_path('app/public'))
        );
        $checks = [
            ['App key is configured', filled(config('app.key')), 'Set APP_KEY with php artisan key:generate on the target host.'],
            ['Database connection works', $databaseAvailable, 'Configure the production database and apply migrations.'],
            ['Database migrations are current', $migrationsCurrent, 'Run php artisan migrate:status, then apply pending migrations with php artisan migrate --force.'],
            ['Local public image link is valid', $publicStorageLinked, 'Run php artisan storage:link on the target host, or configure a shared object-storage disk.'],
            ['Storage and cache directories are writable', is_writable(storage_path()) && is_writable(storage_path('framework')) && is_writable(storage_path('logs')) && is_writable(base_path('bootstrap/cache')), 'Give the web and queue processes write access to storage/ and bootstrap/cache/.'],
        ];
        if ($strict) {
            $checks = array_merge($checks, [
                ['Application URL uses HTTPS', str_starts_with((string) config('app.url'), 'https://'), 'Set APP_URL to the public HTTPS origin and configure trusted proxy headers.'],
                ['Debug output is disabled', config('app.debug') === false, 'Set APP_DEBUG=false.'],
                ['Mail transport is not a test sink', $this->hasDeliveryOnlyMailers((string) config('mail.default')), 'Configure a real mail transport without log/array fallbacks and test a password-reset email.'],
                ['Queue uses a worker-backed connection', filled(config('queue.default')) && ! in_array(config('queue.default'), ['sync', 'null'], true), 'Use a persistent queue connection and run a queue worker.'],
                ['Scheduled backups are enabled', config('backup.enabled') === true, 'Run and restore a backup on disposable data, then set BACKUP_ENABLED=true and configure BACKUP_DUMP_BINARY.'],
            ]);
        }

        $failed = false;
        foreach ($checks as [$label, $passed, $action]) {
            $this->line(($passed ? '[PASS] ' : '[FAIL] ').$label);
            if (! $passed) {
                $this->line('       '.$action);
                $failed = true;
            }
        }

        $this->newLine();
        $this->line('Manual host checks still required: deliver a real password-reset email, confirm queue worker and scheduler are running, test image upload/display, verify HTTPS, and perform a database backup and restore on disposable data.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function hasDeliveryOnlyMailers(string $name, array $visited = []): bool
    {
        if ($name === '' || in_array($name, $visited, true)) {
            return false;
        }

        $mailer = config('mail.mailers.'.$name);
        if (! is_array($mailer) || ! filled($mailer['transport'] ?? null)
            || in_array($mailer['transport'], ['log', 'array'], true)) {
            return false;
        }

        if (! in_array($mailer['transport'], ['failover', 'roundrobin'], true)) {
            return true;
        }

        $children = $mailer['mailers'] ?? [];
        if (! is_array($children) || $children === []) {
            return false;
        }

        foreach ($children as $child) {
            if (! is_string($child) || ! $this->hasDeliveryOnlyMailers($child, [...$visited, $name])) {
                return false;
            }
        }

        return true;
    }
}
