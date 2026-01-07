<?php

declare(strict_types=1);

namespace Daikazu\AssetCleaner\Commands;

use Daikazu\AssetCleaner\BladeCleaner;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class BladeCleanerCleanCommand extends Command
{
    public $signature = 'blade-cleaner:clean
        {--dry-run : Show what would be deleted without actually deleting}
        {--force : Skip confirmation prompt}
        {--trust : One-shot mode: scan and delete without manifest (use with caution)}
        {--no-backup : Do not backup files before deletion}';

    public $description = 'Delete unused Blade components based on the manifest';

    public function handle(BladeCleaner $cleaner): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $trust = (bool) $this->option('trust');

        if ($trust) {
            return $this->handleTrustMode($cleaner, $dryRun, $force);
        }

        return $this->handleManifestMode($cleaner, $dryRun, $force);
    }

    private function handleManifestMode(BladeCleaner $cleaner, bool $dryRun, bool $force): int
    {
        if (! $cleaner->manifest()->exists()) {
            warning('No manifest file found.');
            note('Run `php artisan blade-cleaner:scan` first to generate a manifest.');

            return self::FAILURE;
        }

        $manifest = $cleaner->manifest()->load();
        $components = $cleaner->manifest()->getComponents();

        if ($components->isEmpty()) {
            info('No components in manifest to delete.');
            $cleaner->manifest()->delete();

            return self::SUCCESS;
        }

        info($dryRun ? 'DRY RUN - No files will be deleted' : 'Preparing to delete components...');

        note("Found {$components->count()} components in manifest ({$manifest['total_size_human']})");

        if (! $dryRun && ! $force) {
            $confirmed = confirm(
                label: "Are you sure you want to delete {$components->count()} components?",
                default: false,
            );

            if (! $confirmed) {
                info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        $result = $cleaner->cleanFromManifest($dryRun);

        $this->displayResults($result, $dryRun);

        if (! $dryRun && $result['deleted'] > 0) {
            $cleaner->manifest()->delete();
            note('Manifest file removed.');
        }

        return self::SUCCESS;
    }

    private function handleTrustMode(BladeCleaner $cleaner, bool $dryRun, bool $force): int
    {
        warning('TRUST MODE: Scanning and deleting in one step.');

        if (! $dryRun && ! $force) {
            $confirmed = confirm(
                label: 'This will delete all detected unused components without review. Continue?',
                default: false,
            );

            if (! $confirmed) {
                info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        info($dryRun ? 'DRY RUN - Scanning for unused components...' : 'Scanning and deleting unused components...');

        $result = $cleaner->cleanAll($dryRun);

        note("Scanned {$result['scanned']} total Blade components.");

        $this->displayResults($result, $dryRun);

        return self::SUCCESS;
    }

    /**
     * @param  array{deleted: int, backed_up: int, failed: array<int, string>, total_size: int, scanned?: int}  $result
     */
    private function displayResults(array $result, bool $dryRun): void
    {
        $action = $dryRun ? 'Would delete' : 'Deleted';

        table(
            headers: ['Result', 'Count'],
            rows: [
                [$action, (string) $result['deleted']],
                ['Backed up', (string) $result['backed_up']],
                ['Failed', (string) count($result['failed'])],
                ['Space freed', $this->humanFileSize($result['total_size'])],
            ],
        );

        if (! empty($result['failed'])) {
            error('Some components could not be deleted:');
            foreach ($result['failed'] as $failure) {
                note("  - {$failure}");
            }
        }

        if ($result['deleted'] > 0) {
            info($dryRun
                ? "Would free {$this->humanFileSize($result['total_size'])} of disk space."
                : "Freed {$this->humanFileSize($result['total_size'])} of disk space.");
        } else {
            info('No components to delete.');
        }
    }

    private function humanFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
