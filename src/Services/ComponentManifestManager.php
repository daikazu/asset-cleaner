<?php

declare(strict_types=1);

namespace Daikazu\AssetCleaner\Services;

use Daikazu\AssetCleaner\DTOs\BladeComponent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use RuntimeException;

final class ComponentManifestManager
{
    public function __construct(
        private readonly string $manifestPath,
        private readonly string $basePath,
    ) {}

    /**
     * Generate and save a manifest of unused components.
     *
     * @param  Collection<int, BladeComponent>  $unusedComponents
     */
    public function generate(Collection $unusedComponents, int $totalScanned = 0): void
    {
        $manifest = [
            'generated_at' => now()->toIso8601String(),
            'total_scanned' => $totalScanned,
            'total_unused' => $unusedComponents->count(),
            'total_size' => $unusedComponents->sum(fn (BladeComponent $component) => $component->totalSize),
            'total_size_human' => $this->humanFileSize($unusedComponents->sum(fn (BladeComponent $component) => $component->totalSize)),
            'instructions' => [
                'review' => 'Review the components below and remove any that are actually used.',
                'delete_entry' => 'Remove the entry from the "components" array to keep the component.',
                'clean' => 'Run `php artisan blade-cleaner:clean` to delete remaining components.',
            ],
            'components' => $unusedComponents->map(fn (BladeComponent $component) => $component->jsonSerialize())->values()->all(),
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
     * Get components from the manifest.
     *
     * @return Collection<int, BladeComponent>
     */
    public function getComponents(): Collection
    {
        $manifest = $this->load();

        if ($manifest === null || ! isset($manifest['components'])) {
            return collect();
        }

        return collect($manifest['components'])->map(function (array $data) {
            return BladeComponent::fromManifest($data, $this->basePath);
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
