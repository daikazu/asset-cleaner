<?php

declare(strict_types=1);

namespace Daikazu\AssetCleaner;

use Daikazu\AssetCleaner\Commands\CleanCommand;
use Daikazu\AssetCleaner\Commands\ScanCommand;
use Daikazu\AssetCleaner\Services\AssetDeleter;
use Daikazu\AssetCleaner\Services\AssetScanner;
use Daikazu\AssetCleaner\Services\ManifestManager;
use Daikazu\AssetCleaner\Services\ReferenceSearcher;
use Illuminate\Contracts\Foundation\Application;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class AssetCleanerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('asset-cleaner')
            ->hasConfigFile()
            ->hasCommands([
                ScanCommand::class,
                CleanCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(AssetScanner::class, function (Application $app): AssetScanner {
            /** @var array<string, mixed> $config */
            $config = $app['config']['asset-cleaner'];

            return new AssetScanner(
                scanPaths: $config['scan_paths'],
                imageExtensions: $config['image_extensions'],
                excludePatterns: $config['exclude_patterns'],
                protectedPatterns: $config['protected_patterns'],
                basePath: $app->basePath(),
            );
        });

        $this->app->singleton(ReferenceSearcher::class, function (Application $app): ReferenceSearcher {
            /** @var array<string, mixed> $config */
            $config = $app['config']['asset-cleaner'];

            return new ReferenceSearcher(
                searchPaths: $config['search_paths'],
                searchExtensions: $config['search_extensions'],
                excludePatterns: $config['exclude_patterns'],
                basePath: $app->basePath(),
            );
        });

        $this->app->singleton(ManifestManager::class, function (Application $app): ManifestManager {
            /** @var array<string, mixed> $config */
            $config = $app['config']['asset-cleaner'];

            return new ManifestManager(
                manifestPath: $config['manifest_path'],
                basePath: $app->basePath(),
            );
        });

        $this->app->singleton(AssetDeleter::class, function (Application $app): AssetDeleter {
            /** @var array<string, mixed> $config */
            $config = $app['config']['asset-cleaner'];

            return new AssetDeleter(
                backupBeforeDelete: $config['backup_before_delete'],
                backupPath: $config['backup_path'],
                basePath: $app->basePath(),
            );
        });

        $this->app->singleton(AssetCleaner::class, function (Application $app): AssetCleaner {
            return new AssetCleaner(
                scanner: $app->make(AssetScanner::class),
                searcher: $app->make(ReferenceSearcher::class),
                manifest: $app->make(ManifestManager::class),
                deleter: $app->make(AssetDeleter::class),
            );
        });

        $this->app->alias(AssetCleaner::class, 'asset-cleaner');
    }
}
