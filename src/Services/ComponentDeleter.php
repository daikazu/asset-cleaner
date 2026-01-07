<?php

declare(strict_types=1);

namespace Daikazu\AssetCleaner\Services;

use Daikazu\AssetCleaner\DTOs\BladeComponent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use RuntimeException;

final class ComponentDeleter
{
    public function __construct(
        private readonly bool $backupBeforeDelete,
        private readonly string $backupPath,
        private readonly string $basePath,
    ) {}

    /**
     * Delete components with optional backup.
     *
     * @param  Collection<int, BladeComponent>  $components
     * @return array{deleted: int, backed_up: int, failed: array<int, string>, total_size: int}
     */
    public function delete(Collection $components, bool $dryRun = false): array
    {
        $deleted = 0;
        $backedUp = 0;
        $failed = [];
        $totalSize = 0;
        $timestamp = now()->format('Y-m-d_His');

        foreach ($components as $component) {
            $filesToDelete = $this->getFilesToDelete($component);

            if (empty($filesToDelete)) {
                $failed[] = $component->name.' (no files found)';

                continue;
            }

            if ($dryRun) {
                $deleted++;
                $totalSize += $component->totalSize;

                continue;
            }

            try {
                // Backup all files first
                if ($this->backupBeforeDelete) {
                    foreach ($filesToDelete as $file) {
                        $this->backupFile($file['path'], $file['relative'], $timestamp);
                        $backedUp++;
                    }
                }

                // Delete all files
                foreach ($filesToDelete as $file) {
                    File::delete($file['path']);
                    $this->cleanEmptyDirectories(dirname($file['path']));
                }

                $deleted++;
                $totalSize += $component->totalSize;
            } catch (RuntimeException $e) {
                $failed[] = $component->name.' ('.$e->getMessage().')';
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
     * Get all files that need to be deleted for a component.
     *
     * @return array<int, array{path: string, relative: string}>
     */
    private function getFilesToDelete(BladeComponent $component): array
    {
        $files = [];

        // Include view file (if exists)
        if ($component->viewPath !== null && File::exists($component->viewPath)) {
            $files[] = [
                'path' => $component->viewPath,
                'relative' => $component->viewRelativePath,
            ];
        }

        // Include class file for class-based components
        if ($component->isClassBased && $component->classPath !== null && File::exists($component->classPath)) {
            $files[] = [
                'path' => $component->classPath,
                'relative' => $component->classRelativePath,
            ];
        }

        return $files;
    }

    /**
     * Backup a file before deletion.
     */
    private function backupFile(string $filePath, string $relativePath, string $timestamp): void
    {
        $backupDir = $this->basePath.DIRECTORY_SEPARATOR.$this->backupPath;
        $backupFile = $backupDir.DIRECTORY_SEPARATOR.$timestamp.DIRECTORY_SEPARATOR.$relativePath;

        $backupFileDir = dirname($backupFile);
        if (! File::isDirectory($backupFileDir)) {
            File::makeDirectory($backupFileDir, 0755, true);
        }

        if (! File::copy($filePath, $backupFile)) {
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

        // Don't delete the base path or its immediate children (app, resources, etc.)
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
