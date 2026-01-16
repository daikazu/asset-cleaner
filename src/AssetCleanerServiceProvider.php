<?php

declare(strict_types=1);

namespace Daikazu\AssetCleaner;

use Daikazu\AssetCleaner\Commands\BladeCleanerCleanCommand;
use Daikazu\AssetCleaner\Commands\BladeCleanerScanCommand;
use Daikazu\AssetCleaner\Commands\CleanCommand;
use Daikazu\AssetCleaner\Commands\ScanCommand;
use Daikazu\AssetCleaner\Contracts\PatternGenerator;
use Daikazu\AssetCleaner\PatternGenerators\BladeIconsPatternGenerator;
use Daikazu\AssetCleaner\Services\AssetDeleter;
use Daikazu\AssetCleaner\Services\AssetScanner;
use Daikazu\AssetCleaner\Services\ComponentDeleter;
use Daikazu\AssetCleaner\Services\ComponentManifestManager;
use Daikazu\AssetCleaner\Services\ComponentReferenceSearcher;
use Daikazu\AssetCleaner\Services\ComponentScanner;
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
                BladeCleanerScanCommand::class,
                BladeCleanerCleanCommand::class,
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
                rootConfigFiles: $config['root_config_files'] ?? [],
                patternGenerators: $this->resolvePatternGenerators($config['pattern_generators'] ?? []),
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

        // Blade Cleaner Services
        $this->app->singleton(ComponentScanner::class, function (Application $app): ComponentScanner {
            /** @var array<string, mixed> $config */
            $config = $app['config']['asset-cleaner']['blade_cleaner'];

            return new ComponentScanner(
                anonymousPaths: $config['anonymous_paths'],
                classPaths: $config['class_paths'],
                excludePatterns: $config['exclude_patterns'],
                protectedPatterns: $config['protected_patterns'],
                basePath: $app->basePath(),
            );
        });

        $this->app->singleton(ComponentReferenceSearcher::class, function (Application $app): ComponentReferenceSearcher {
            /** @var array<string, mixed> $config */
            $config = $app['config']['asset-cleaner']['blade_cleaner'];

            return new ComponentReferenceSearcher(
                searchPaths: $config['search_paths'],
                searchExtensions: $config['search_extensions'],
                excludePatterns: $config['exclude_patterns'],
                basePath: $app->basePath(),
            );
        });

        $this->app->singleton(ComponentManifestManager::class, function (Application $app): ComponentManifestManager {
            /** @var array<string, mixed> $config */
            $config = $app['config']['asset-cleaner']['blade_cleaner'];

            return new ComponentManifestManager(
                manifestPath: $config['manifest_path'],
                basePath: $app->basePath(),
            );
        });

        $this->app->singleton(ComponentDeleter::class, function (Application $app): ComponentDeleter {
            /** @var array<string, mixed> $config */
            $config = $app['config']['asset-cleaner'];
            /** @var array<string, mixed> $bladeConfig */
            $bladeConfig = $config['blade_cleaner'];

            return new ComponentDeleter(
                backupBeforeDelete: $config['backup_before_delete'],
                backupPath: $bladeConfig['backup_path'],
                basePath: $app->basePath(),
            );
        });

        $this->app->singleton(BladeCleaner::class, function (Application $app): BladeCleaner {
            return new BladeCleaner(
                scanner: $app->make(ComponentScanner::class),
                searcher: $app->make(ComponentReferenceSearcher::class),
                manifest: $app->make(ComponentManifestManager::class),
                deleter: $app->make(ComponentDeleter::class),
            );
        });

        $this->app->alias(BladeCleaner::class, 'blade-cleaner');
    }

    /**
     * Resolve enabled pattern generators based on config.
     *
     * @param  array<string, mixed>  $generatorConfigs
     * @return array<int, PatternGenerator>
     */
    private function resolvePatternGenerators(array $generatorConfigs): array
    {
        $generators = [];

        /** @var array<string, class-string<PatternGenerator>> $availableGenerators */
        $availableGenerators = [
            'blade_icons' => BladeIconsPatternGenerator::class,
        ];

        foreach ($availableGenerators as $key => $generatorClass) {
            $setting = $generatorConfigs[$key] ?? 'auto';

            $enabled = match ($setting) {
                true => true,
                false => false,
                'auto' => $generatorClass::isAvailable(),
                default => false,
            };

            if ($enabled) {
                $generators[] = new $generatorClass;
            }
        }

        return $generators;
    }
}
