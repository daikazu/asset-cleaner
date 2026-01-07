<?php

declare(strict_types=1);

namespace Daikazu\AssetCleaner\Facades;

use Daikazu\AssetCleaner\DTOs\BladeComponent;
use Daikazu\AssetCleaner\Services\ComponentManifestManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Collection<int, BladeComponent> scan()
 * @method static Collection<int, BladeComponent> findUnused()
 * @method static void generateManifest()
 * @method static ComponentManifestManager manifest()
 * @method static array{deleted: int, backed_up: int, failed: array<int, string>, total_size: int} cleanFromManifest(bool $dryRun = false)
 * @method static array{scanned: int, deleted: int, backed_up: int, failed: array<int, string>, total_size: int} cleanAll(bool $dryRun = false)
 * @method static Collection<int, string> findReferences(BladeComponent $component)
 * @method static array{total: int, unused: int, used: int, total_size: int, unused_size: int} getStatistics()
 *
 * @see \Daikazu\AssetCleaner\BladeCleaner
 */
class BladeCleaner extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Daikazu\AssetCleaner\BladeCleaner::class;
    }
}
