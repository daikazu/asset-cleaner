<?php

declare(strict_types=1);

namespace Daikazu\AssetCleaner;

use Daikazu\AssetCleaner\DTOs\ImageAsset;
use Daikazu\AssetCleaner\Services\AssetDeleter;
use Daikazu\AssetCleaner\Services\AssetScanner;
use Daikazu\AssetCleaner\Services\ManifestManager;
use Daikazu\AssetCleaner\Services\ReferenceSearcher;
use Illuminate\Support\Collection;

final class AssetCleaner
{
    public function __construct(
        private readonly AssetScanner $scanner,
        private readonly ReferenceSearcher $searcher,
        private readonly ManifestManager $manifest,
        private readonly AssetDeleter $deleter,
    ) {}

    /**
     * Scan for all image assets in configured directories.
     *
     * @return Collection<int, ImageAsset>
     */
    public function scan(): Collection
    {
        return $this->scanner->scan();
    }

    /**
     * Find unused assets by comparing against codebase references.
     *
     * @return Collection<int, ImageAsset>
     */
    public function findUnused(): Collection
    {
        $assets = $this->scan();
        $unused = $this->searcher->findUnusedAssets($assets);

        // Filter out protected assets
        return $unused->reject(fn (ImageAsset $asset) => $this->scanner->isProtected($asset))->values();
    }

    /**
     * Generate a manifest of unused assets.
     */
    public function generateManifest(): void
    {
        $allAssets = $this->scan();
        $unused = $this->findUnused();

        $this->manifest->generate($unused, $allAssets->count());
    }

    /**
     * Get the manifest manager.
     */
    public function manifest(): ManifestManager
    {
        return $this->manifest;
    }

    /**
     * Delete assets from the manifest.
     *
     * @return array{deleted: int, backed_up: int, failed: array<int, string>, total_size: int}
     */
    public function cleanFromManifest(bool $dryRun = false): array
    {
        $assets = $this->manifest->getAssets();

        return $this->deleter->delete($assets, $dryRun);
    }

    /**
     * One-shot: find unused assets and delete them immediately.
     *
     * @return array{scanned: int, deleted: int, backed_up: int, failed: array<int, string>, total_size: int}
     */
    public function cleanAll(bool $dryRun = false): array
    {
        $allAssets = $this->scan();
        $unused = $this->findUnused();
        $result = $this->deleter->delete($unused, $dryRun);

        return [
            'scanned' => $allAssets->count(),
            ...$result,
        ];
    }

    /**
     * Find where an asset is referenced.
     *
     * @return Collection<int, string>
     */
    public function findReferences(ImageAsset $asset): Collection
    {
        return $this->searcher->findReferences($asset);
    }

    /**
     * Get statistics about assets.
     *
     * @return array{total: int, unused: int, used: int, total_size: int, unused_size: int}
     */
    public function getStatistics(): array
    {
        $all = $this->scan();
        $unused = $this->findUnused();

        return [
            'total' => $all->count(),
            'unused' => $unused->count(),
            'used' => $all->count() - $unused->count(),
            'total_size' => $all->sum(fn (ImageAsset $a) => $a->size),
            'unused_size' => $unused->sum(fn (ImageAsset $a) => $a->size),
        ];
    }
}
