<?php

declare(strict_types=1);

namespace Daikazu\AssetCleaner\Services;

use Daikazu\AssetCleaner\DTOs\ImageAsset;
use Illuminate\Support\Collection;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class AssetScanner
{
    /**
     * @param  array<int, string>  $scanPaths
     * @param  array<int, string>  $imageExtensions
     * @param  array<int, string>  $excludePatterns
     * @param  array<int, string>  $protectedPatterns
     */
    public function __construct(
        private readonly array $scanPaths,
        private readonly array $imageExtensions,
        private readonly array $excludePatterns,
        private readonly array $protectedPatterns,
        private readonly string $basePath,
    ) {}

    /**
     * Scan configured directories for image assets.
     *
     * @return Collection<int, ImageAsset>
     */
    public function scan(): Collection
    {
        $assets = collect();

        foreach ($this->scanPaths as $scanPath) {
            $fullPath = $this->basePath.DIRECTORY_SEPARATOR.$scanPath;

            if (! is_dir($fullPath)) {
                continue;
            }

            $assets = $assets->merge($this->scanDirectory($fullPath));
        }

        return $assets->values();
    }

    /**
     * Scan a single directory recursively for images.
     *
     * @return Collection<int, ImageAsset>
     */
    private function scanDirectory(string $directory): Collection
    {
        $assets = collect();

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $path = $file->getPathname();

            if (! $this->isImageFile($path)) {
                continue;
            }

            if ($this->isExcluded($path)) {
                continue;
            }

            $assets->push(ImageAsset::fromPath($path, $this->basePath));
        }

        return $assets;
    }

    /**
     * Check if a file is an image based on extension.
     */
    private function isImageFile(string $path): bool
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, $this->imageExtensions, true);
    }

    /**
     * Check if a path matches any exclude pattern.
     */
    private function isExcluded(string $path): bool
    {
        $relativePath = str_replace($this->basePath.DIRECTORY_SEPARATOR, '', $path);

        foreach ($this->excludePatterns as $pattern) {
            if ($this->matchesGlobPattern($relativePath, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if an asset is protected from deletion.
     */
    public function isProtected(ImageAsset $asset): bool
    {
        foreach ($this->protectedPatterns as $pattern) {
            if ($this->matchesGlobPattern($asset->relativePath, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match a path against a glob-style pattern.
     */
    private function matchesGlobPattern(string $path, string $pattern): bool
    {
        // Normalize path separators
        $path = str_replace('\\', '/', $path);
        $pattern = str_replace('\\', '/', $pattern);

        // Convert glob pattern to regex
        $regex = $this->globToRegex($pattern);

        return (bool) preg_match($regex, $path);
    }

    /**
     * Convert a glob pattern to a regular expression.
     */
    private function globToRegex(string $pattern): string
    {
        $regex = preg_quote($pattern, '#');

        // Replace escaped glob patterns with regex equivalents
        $regex = str_replace(
            ['\*\*/', '\*\*', '\*', '\?'],
            ['(.+/)?', '.*', '[^/]*', '.'],
            $regex
        );

        return '#^'.$regex.'$#i';
    }
}
