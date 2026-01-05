<?php

declare(strict_types=1);

namespace Daikazu\AssetCleaner\Services;

use Daikazu\AssetCleaner\DTOs\ImageAsset;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use RuntimeException;

final class AssetDeleter
{
    public function __construct(
        private readonly bool $backupBeforeDelete,
        private readonly string $backupPath,
        private readonly string $basePath,
    ) {}

    /**
     * Delete assets with optional backup.
     *
     * @param  Collection<int, ImageAsset>  $assets
     * @return array{deleted: int, backed_up: int, failed: array<int, string>, total_size: int}
     */
    public function delete(Collection $assets, bool $dryRun = false): array
    {
        $deleted = 0;
        $backedUp = 0;
        $failed = [];
        $totalSize = 0;
        $timestamp = now()->format('Y-m-d_His');

        foreach ($assets as $asset) {
            if (! File::exists($asset->path)) {
                $failed[] = $asset->relativePath.' (file not found)';

                continue;
            }

            if ($dryRun) {
                $deleted++;
                $totalSize += $asset->size;

                continue;
            }

            try {
                if ($this->backupBeforeDelete) {
                    $this->backupAsset($asset, $timestamp);
                    $backedUp++;
                }

                File::delete($asset->path);
                $deleted++;
                $totalSize += $asset->size;

                // Clean up empty directories
                $this->cleanEmptyDirectories(dirname($asset->path));
            } catch (RuntimeException $e) {
                $failed[] = $asset->relativePath.' ('.$e->getMessage().')';
            }
        }

        return [
            'deleted' => $deleted,
            'backed_up' => $backedUp,
            'failed' => $failed,
            'total_size' => $totalSize,
        ];
    }

    /**
     * Backup an asset before deletion.
     */
    private function backupAsset(ImageAsset $asset, string $timestamp): void
    {
        $backupDir = $this->basePath.DIRECTORY_SEPARATOR.$this->backupPath;
        $backupFile = $backupDir.DIRECTORY_SEPARATOR.$timestamp.DIRECTORY_SEPARATOR.$asset->relativePath;

        $backupFileDir = dirname($backupFile);
        if (! File::isDirectory($backupFileDir)) {
            File::makeDirectory($backupFileDir, 0755, true);
        }

        if (! File::copy($asset->path, $backupFile)) {
            throw new RuntimeException('Failed to create backup');
        }
    }

    /**
     * Remove empty directories up the tree.
     */
    private function cleanEmptyDirectories(string $directory): void
    {
        // Don't delete directories outside the base path
        if (! str_starts_with($directory, $this->basePath)) {
            return;
        }

        // Don't delete the base path or its immediate children (public, resources, etc.)
        $relativePath = str_replace($this->basePath.DIRECTORY_SEPARATOR, '', $directory);
        if (empty($relativePath) || ! str_contains($relativePath, DIRECTORY_SEPARATOR)) {
            return;
        }

        if (! File::isDirectory($directory)) {
            return;
        }

        $files = File::files($directory);
        $directories = File::directories($directory);

        if (count($files) === 0 && count($directories) === 0) {
            File::deleteDirectory($directory);
            // Recursively check parent
            $this->cleanEmptyDirectories(dirname($directory));
        }
    }

    /**
     * Get backup path.
     */
    public function getBackupPath(): string
    {
        return $this->basePath.DIRECTORY_SEPARATOR.$this->backupPath;
    }
}
