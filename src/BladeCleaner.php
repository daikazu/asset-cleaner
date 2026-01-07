<?php

declare(strict_types=1);

namespace Daikazu\AssetCleaner;

use Daikazu\AssetCleaner\DTOs\BladeComponent;
use Daikazu\AssetCleaner\Services\ComponentDeleter;
use Daikazu\AssetCleaner\Services\ComponentManifestManager;
use Daikazu\AssetCleaner\Services\ComponentReferenceSearcher;
use Daikazu\AssetCleaner\Services\ComponentScanner;
use Illuminate\Support\Collection;

final class BladeCleaner
{
    public function __construct(
        private readonly ComponentScanner $scanner,
        private readonly ComponentReferenceSearcher $searcher,
        private readonly ComponentManifestManager $manifest,
        private readonly ComponentDeleter $deleter,
    ) {}

    /**
     * Scan for all Blade components in configured directories.
     *
     * @return Collection<int, BladeComponent>
     */
    public function scan(): Collection
    {
        return $this->scanner->scan();
    }

    /**
     * Find unused components by comparing against codebase references.
     *
     * @return Collection<int, BladeComponent>
     */
    public function findUnused(): Collection
    {
        $components = $this->scan();
        $unused = $this->searcher->findUnusedComponents($components);

        // Filter out protected components
        return $unused->reject(fn (BladeComponent $component) => $this->scanner->isProtected($component))->values();
    }

    /**
     * Generate a manifest of unused components.
     */
    public function generateManifest(): void
    {
        $allComponents = $this->scan();
        $unused = $this->findUnused();

        $this->manifest->generate($unused, $allComponents->count());
    }

    /**
     * Get the manifest manager.
     */
    public function manifest(): ComponentManifestManager
    {
        return $this->manifest;
    }

    /**
     * Delete components from the manifest.
     *
     * @return array{deleted: int, backed_up: int, failed: array<int, string>, total_size: int}
     */
    public function cleanFromManifest(bool $dryRun = false): array
    {
        $components = $this->manifest->getComponents();

        return $this->deleter->delete($components, $dryRun);
    }

    /**
     * One-shot: find unused components and delete them immediately.
     *
     * @return array{scanned: int, deleted: int, backed_up: int, failed: array<int, string>, total_size: int}
     */
    public function cleanAll(bool $dryRun = false): array
    {
        $allComponents = $this->scan();
        $unused = $this->findUnused();
        $result = $this->deleter->delete($unused, $dryRun);

        return [
            'scanned' => $allComponents->count(),
            ...$result,
        ];
    }

    /**
     * Find where a component is referenced.
     *
     * @return Collection<int, string>
     */
    public function findReferences(BladeComponent $component): Collection
    {
        return $this->searcher->findReferences($component);
    }

    /**
     * Get statistics about components.
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
            'total_size' => $all->sum(fn (BladeComponent $c) => $c->totalSize),
            'unused_size' => $unused->sum(fn (BladeComponent $c) => $c->totalSize),
        ];
    }
}
