<?php

declare(strict_types=1);

namespace Daikazu\AssetCleaner\Facades;

use Daikazu\AssetCleaner\DTOs\ImageAsset;
use Daikazu\AssetCleaner\Services\ManifestManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Collection<int, ImageAsset> scan()
 * @method static Collection<int, ImageAsset> findUnused()
 * @method static void generateManifest()
 * @method static ManifestManager manifest()
 * @method static array{deleted: int, backed_up: int, failed: array<int, string>, total_size: int} cleanFromManifest(bool $dryRun = false)
 * @method static array{scanned: int, deleted: int, backed_up: int, failed: array<int, string>, total_size: int} cleanAll(bool $dryRun = false)
 * @method static Collection<int, string> findReferences(ImageAsset $asset)
 * @method static array{total: int, unused: int, used: int, total_size: int, unused_size: int} getStatistics()
 *
 * @see \Daikazu\AssetCleaner\AssetCleaner
 */
class AssetCleaner extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Daikazu\AssetCleaner\AssetCleaner::class;
    }
}
