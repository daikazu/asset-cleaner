<?php

declare(strict_types=1);

namespace Daikazu\AssetCleaner\Commands;

use Daikazu\AssetCleaner\AssetCleaner;
use Illuminate\Console\Command;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class ScanCommand extends Command
{
    public $signature = 'asset-cleaner:scan
        {--stats : Show statistics only, do not generate manifest}';

    public $description = 'Scan for unused image assets and generate a manifest file';

    public function handle(AssetCleaner $cleaner): int
    {
        info('Scanning for image assets...');

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
            info('No unused assets found. Your project is clean!');
            $cleaner->manifest()->delete();

            return self::SUCCESS;
        }

        note("Scanned {$manifest['total_scanned']} image assets.");
        warning("Found {$manifest['total_unused']} unused assets ({$manifest['total_size_human']})");

        // Show table of unused assets (limit to first 20)
        $assets = collect($manifest['assets']);

        if ($assets->isNotEmpty()) {
            $rows = $assets->take(20)->map(fn (array $asset) => [
                $asset['path'],
                $asset['size_human'],
            ])->all();

            table(
                headers: ['Path', 'Size'],
                rows: $rows,
            );

            if ($assets->count() > 20) {
                note('... and '.($assets->count() - 20).' more assets.');
            }
        }

        info("Manifest saved to: {$manifestPath}");
        note('Review the manifest and run `php artisan asset-cleaner:clean` to delete unused assets.');

        return self::SUCCESS;
    }

    private function showStatistics(AssetCleaner $cleaner): int
    {
        $stats = $cleaner->getStatistics();

        table(
            headers: ['Metric', 'Value'],
            rows: [
                ['Total image assets', (string) $stats['total']],
                ['Unused assets', (string) $stats['unused']],
                ['Used assets', (string) $stats['used']],
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
