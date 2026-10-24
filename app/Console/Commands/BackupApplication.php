<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class BackupApplication extends Command
{
    protected $signature = 'app:backup';

    protected $description = 'Create a private database and uploaded-files backup';

    public function handle(): int
    {
        if (! class_exists(ZipArchive::class)) {
            $this->error('The PHP zip extension is required for backups.');

            return self::FAILURE;
        }

        $connection = DB::connection();
        if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->error('This backup command supports MySQL and MariaDB only.');

            return self::FAILURE;
        }

        $directory = storage_path('app/private/backups');
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            $this->error('Could not create the private backup directory.');

            return self::FAILURE;
        }

        $suffix = now()->format('Ymd-His').'-'.Str::lower(Str::random(6));
        $dumpPath = $directory.DIRECTORY_SEPARATOR.'.'.$suffix.'.sql';
        $partialPath = $directory.DIRECTORY_SEPARATOR.'.'.$suffix.'.zip.part';
        $archivePath = $directory.DIRECTORY_SEPARATOR.'autocare-'.$suffix.'.zip';
        $zip = new ZipArchive;
        $zipOpen = false;
        $backupPrefix = strtolower(rtrim(str_replace('\\', '/', realpath($directory) ?: $directory), '/').'/');

        try {
            $command = [config('backup.dump_binary', 'mysqldump'), '--single-transaction', '--quick', '--routines', '--triggers', '--events', '--default-character-set=utf8mb4'];
            if ($socket = $connection->getConfig('unix_socket')) {
                $command[] = '--socket='.$socket;
            } else {
                $command[] = '--host='.($connection->getConfig('host') ?: '127.0.0.1');
                $command[] = '--port='.($connection->getConfig('port') ?: '3306');
            }
            $command[] = '--user='.$connection->getConfig('username');
            $command[] = '--result-file='.$dumpPath;
            $command[] = $connection->getDatabaseName();

            $process = new Process($command, base_path(), ['MYSQL_PWD' => (string) $connection->getConfig('password')]);
            $process->setTimeout(3600);
            $process->mustRun();

            if (! is_file($dumpPath) || filesize($dumpPath) === 0) {
                throw new \RuntimeException('The database dump was empty.');
            }

            if ($zip->open($partialPath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
                throw new \RuntimeException('Could not create the backup archive.');
            }
            $zipOpen = true;

            if (! $zip->addFile($dumpPath, 'database.sql')) {
                throw new \RuntimeException('Could not add the database dump to the backup archive.');
            }
            $fileCount = 0;
            foreach (['public', 'private'] as $area) {
                $root = storage_path('app/'.$area);
                if (! is_dir($root)) {
                    continue;
                }

                $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
                foreach ($files as $file) {
                    $path = $file->getPathname();
                    $normalizedPath = str_replace('\\', '/', $file->getRealPath() ?: $path);
                    if (! $file->isFile() || $file->isLink() || str_starts_with(strtolower($normalizedPath), $backupPrefix)) {
                        continue;
                    }

                    $relativePath = substr(str_replace('\\', '/', $path), strlen(str_replace('\\', '/', $root)) + 1);
                    if (! $zip->addFile($path, 'uploads/'.$area.'/'.$relativePath)) {
                        throw new \RuntimeException('Could not add an uploaded file to the backup archive.');
                    }
                    $fileCount++;
                }
            }

            if (! $zip->addFromString('manifest.json', json_encode([
                'created_at' => now()->toIso8601String(),
                'database' => $connection->getDatabaseName(),
                'database_sha256' => hash_file('sha256', $dumpPath),
                'upload_files' => $fileCount,
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT))) {
                throw new \RuntimeException('Could not add the backup manifest.');
            }

            $closed = $zip->close();
            $zipOpen = false;
            if (! $closed) {
                throw new \RuntimeException('Could not finish the backup archive.');
            }
            if (! rename($partialPath, $archivePath)) {
                throw new \RuntimeException('Could not finalize the backup archive.');
            }

            $this->info('Backup created: '.$archivePath);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Backup failed: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            if ($zipOpen) {
                $zip->close();
            }
            if (is_file($dumpPath)) {
                unlink($dumpPath);
            }
            if (is_file($partialPath)) {
                unlink($partialPath);
            }
        }
    }
}
