<?php

declare(strict_types=1);

namespace Daikazu\AssetCleaner\Services;

use Daikazu\AssetCleaner\Contracts\PatternGenerator;
use Daikazu\AssetCleaner\DTOs\ImageAsset;
use Illuminate\Support\Collection;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ReferenceSearcher
{
    /**
     * @param  array<int, string>  $searchPaths
     * @param  array<int, string>  $searchExtensions
     * @param  array<int, string>  $excludePatterns
     * @param  array<int, string>  $rootConfigFiles
     * @param  array<int, PatternGenerator>  $patternGenerators
     */
    public function __construct(
        private readonly array $searchPaths,
        private readonly array $searchExtensions,
        private readonly array $excludePatterns,
        private readonly string $basePath,
        private readonly array $rootConfigFiles = [],
        private readonly array $patternGenerators = [],
    ) {}

    /**
     * Find all assets that have no references in the codebase.
     *
     * @param  Collection<int, ImageAsset>  $assets
     * @return Collection<int, ImageAsset>
     */
    public function findUnusedAssets(Collection $assets): Collection
    {
        // Build a searchable index of file contents
        $searchableContent = $this->buildSearchableContent();

        return $assets->filter(function (ImageAsset $asset) use ($searchableContent) {
            return ! $this->hasReference($asset, $searchableContent);
        })->values();
    }

    /**
     * Find all files where an asset is referenced.
     *
     * @return Collection<int, string>
     */
    public function findReferences(ImageAsset $asset): Collection
    {
        $references = collect();

        foreach ($this->getSearchableFiles() as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            if ($this->contentReferencesAsset($content, $asset)) {
                $relativePath = str_replace($this->basePath.DIRECTORY_SEPARATOR, '', $file);
                $references->push($relativePath);
            }
        }

        return $references;
    }

    /**
     * Build a concatenated string of all searchable file contents.
     */
    private function buildSearchableContent(): string
    {
        $content = '';

        foreach ($this->getSearchableFiles() as $file) {
            $fileContent = file_get_contents($file);
            if ($fileContent !== false) {
                $content .= $fileContent."\n";
            }
        }

        return $content;
    }

    /**
     * Get all files that should be searched for references.
     *
     * @return array<int, string>
     */
    private function getSearchableFiles(): array
    {
        $files = [];

        // Search configured directories
        foreach ($this->searchPaths as $searchPath) {
            $fullPath = $this->basePath.DIRECTORY_SEPARATOR.$searchPath;

            if (! is_dir($fullPath)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $path = $file->getPathname();

                if (! $this->isSearchableFile($path)) {
                    continue;
                }

                if ($this->isExcluded($path)) {
                    continue;
                }

                $files[] = $path;
            }
        }

        // Add root config files (e.g., tailwind.config.js)
        foreach ($this->rootConfigFiles as $configFile) {
            $fullPath = $this->basePath.DIRECTORY_SEPARATOR.$configFile;
            if (is_file($fullPath)) {
                $files[] = $fullPath;
            }
        }

        return $files;
    }

    /**
     * Check if a file should be searched based on extension.
     */
    private function isSearchableFile(string $path): bool
    {
        $filename = basename($path);

        // Check for compound extensions like .blade.php
        foreach ($this->searchExtensions as $extension) {
            if (str_ends_with(strtolower($filename), '.'.strtolower($extension))) {
                return true;
            }
        }

        return false;
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
     * Check if an asset is referenced in content.
     */
    private function hasReference(ImageAsset $asset, string $content): bool
    {
        return $this->contentReferencesAsset($content, $asset);
    }

    /**
     * Check if content references an asset using multiple strategies.
     */
    private function contentReferencesAsset(string $content, ImageAsset $asset): bool
    {
        $searches = $this->getSearchPatterns($asset);

        foreach ($searches as $search) {
            if (stripos($content, $search) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate search patterns for an asset.
     *
     * @return array<int, string>
     */
    private function getSearchPatterns(ImageAsset $asset): array
    {
        $patterns = [];

        // Full relative path (normalized)
        $patterns[] = str_replace('\\', '/', $asset->relativePath);

        // Path without leading public/ (for asset() helper)
        $patterns[] = preg_replace('#^public/#', '', str_replace('\\', '/', $asset->relativePath));

        // Just the filename
        $patterns[] = $asset->filename;

        // Filename without extension (for dynamic references)
        $patterns[] = pathinfo($asset->filename, PATHINFO_FILENAME);

        // URL-encoded variants
        $patterns[] = rawurlencode($asset->filename);

        // Add patterns from registered pattern generators
        foreach ($this->patternGenerators as $generator) {
            if ($generator->supports($asset)) {
                $patterns = array_merge($patterns, $generator->generate($asset));
            }
        }

        return array_unique(array_filter($patterns));
    }

    /**
     * Match a path against a glob-style pattern.
     */
    private function matchesGlobPattern(string $path, string $pattern): bool
    {
        $path = str_replace('\\', '/', $path);
        $pattern = str_replace('\\', '/', $pattern);

        $regex = $this->globToRegex($pattern);

        return (bool) preg_match($regex, $path);
    }

    /**
     * Convert a glob pattern to a regular expression.
     */
    private function globToRegex(string $pattern): string
    {
        $regex = preg_quote($pattern, '#');

        $regex = str_replace(
            ['\*\*/', '\*\*', '\*', '\?'],
            ['(.+/)?', '.*', '[^/]*', '.'],
            $regex
        );

        return '#^'.$regex.'$#i';
    }
}
