<?php

declare(strict_types=1);

namespace Daikazu\AssetCleaner\Commands;

use Daikazu\AssetCleaner\BladeCleaner;
use Illuminate\Console\Command;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class BladeCleanerScanCommand extends Command
{
    public $signature = 'blade-cleaner:scan
        {--stats : Show statistics only, do not generate manifest}';

    public $description = 'Scan for unused Blade components and generate a manifest file';

    public function handle(BladeCleaner $cleaner): int
    {
        info('Scanning for Blade components...');

        if ($this->option('stats')) {
            return $this->showStatistics($cleaner);
        }

        $cleaner->generateManifest();

        $manifest = $cleaner->manifest()->load();

        if ($manifest === null) {
            warning('Failed to generate manifest.');

            return self::FAILURE;
        }

        $manifestPath = $cleaner->manifest()->getManifestPath();

        if ($manifest['total_unused'] === 0) {
            info('No unused components found. Your project is clean!');
            $cleaner->manifest()->delete();

            return self::SUCCESS;
        }

        note("Scanned {$manifest['total_scanned']} Blade components.");
        warning("Found {$manifest['total_unused']} unused components ({$manifest['total_size_human']})");

        // Show table of unused components (limit to first 20)
        $components = collect($manifest['components']);

        if ($components->isNotEmpty()) {
            $rows = $components->take(20)->map(fn (array $component) => [
                $component['name'],
                $component['is_class_based'] ? 'Class-based' : 'Anonymous',
                $component['size_human'],
            ])->all();

            table(
                headers: ['Component', 'Type', 'Size'],
                rows: $rows,
            );

            if ($components->count() > 20) {
                note('... and '.($components->count() - 20).' more components.');
            }
        }

        info("Manifest saved to: {$manifestPath}");
        note('Review the manifest and run `php artisan blade-cleaner:clean` to delete unused components.');

        return self::SUCCESS;
    }

    private function showStatistics(BladeCleaner $cleaner): int
    {
        $stats = $cleaner->getStatistics();

        table(
            headers: ['Metric', 'Value'],
            rows: [
                ['Total Blade components', (string) $stats['total']],
                ['Unused components', (string) $stats['unused']],
                ['Used components', (string) $stats['used']],
                ['Total size', $this->humanFileSize($stats['total_size'])],
                ['Unused size', $this->humanFileSize($stats['unused_size'])],
            ],
        );

        return self::SUCCESS;
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
