<?php

declare(strict_types=1);

namespace Daikazu\AssetCleaner\Services;

use Daikazu\AssetCleaner\DTOs\ImageAsset;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use RuntimeException;

final class ManifestManager
{
    public function __construct(
        private readonly string $manifestPath,
        private readonly string $basePath,
    ) {}

    /**
     * Generate and save a manifest of unused assets.
     *
     * @param  Collection<int, ImageAsset>  $unusedAssets
     */
    public function generate(Collection $unusedAssets, int $totalScanned = 0): void
    {
        $manifest = [
            'generated_at' => now()->toIso8601String(),
            'total_scanned' => $totalScanned,
            'total_unused' => $unusedAssets->count(),
            'total_size' => $unusedAssets->sum(fn (ImageAsset $asset) => $asset->size),
            'total_size_human' => $this->humanFileSize($unusedAssets->sum(fn (ImageAsset $asset) => $asset->size)),
            'instructions' => [
                'review' => 'Review the assets below and remove any that are actually used.',
                'delete_entry' => 'Remove the entry from the "assets" array to keep the file.',
                'clean' => 'Run `php artisan asset-cleaner:clean` to delete remaining assets.',
            ],
            'assets' => $unusedAssets->map(fn (ImageAsset $asset) => $asset->jsonSerialize())->values()->all(),
        ];

        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException('Failed to encode manifest to JSON');
        }

        File::put($this->getFullManifestPath(), $json);
    }

    /**
     * Load the manifest file.
     *
     * @return array<string, mixed>|null
     */
    public function load(): ?array
    {
        $path = $this->getFullManifestPath();

        if (! File::exists($path)) {
            return null;
        }

        $content = File::get($path);
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * Get assets from the manifest.
     *
     * @return Collection<int, ImageAsset>
     */
    public function getAssets(): Collection
    {
        $manifest = $this->load();

        if ($manifest === null || ! isset($manifest['assets'])) {
            return collect();
        }

        return collect($manifest['assets'])->map(function (array $data) {
            return new ImageAsset(
                path: $this->basePath.DIRECTORY_SEPARATOR.$data['path'],
                relativePath: $data['path'],
                filename: $data['filename'],
                extension: $data['extension'],
                size: $data['size'],
                modifiedAt: isset($data['modified_at']) ? strtotime($data['modified_at']) : null,
            );
        });
    }

    /**
     * Check if the manifest file exists.
     */
    public function exists(): bool
    {
        return File::exists($this->getFullManifestPath());
    }

    /**
     * Delete the manifest file.
     */
    public function delete(): bool
    {
        $path = $this->getFullManifestPath();

        if (! File::exists($path)) {
            return false;
        }

        return File::delete($path);
    }

    /**
     * Get the full path to the manifest file.
     */
    public function getFullManifestPath(): string
    {
        return $this->basePath.DIRECTORY_SEPARATOR.$this->manifestPath;
    }

    /**
     * Get the relative manifest path.
     */
    public function getManifestPath(): string
    {
        return $this->manifestPath;
    }

    /**
     * Convert bytes to human readable format.
     */
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
