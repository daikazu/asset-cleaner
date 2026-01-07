<?php

declare(strict_types=1);

namespace Daikazu\AssetCleaner\Services;

use Daikazu\AssetCleaner\DTOs\BladeComponent;
use Illuminate\Support\Collection;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ComponentReferenceSearcher
{
    /**
     * @param  array<int, string>  $searchPaths
     * @param  array<int, string>  $searchExtensions
     * @param  array<int, string>  $excludePatterns
     */
    public function __construct(
        private readonly array $searchPaths,
        private readonly array $searchExtensions,
        private readonly array $excludePatterns,
        private readonly string $basePath,
    ) {}

    /**
     * Find all components that have no references in the codebase.
     *
     * @param  Collection<int, BladeComponent>  $components
     * @return Collection<int, BladeComponent>
     */
    public function findUnusedComponents(Collection $components): Collection
    {
        // Build a searchable index of file contents
        $searchableContent = $this->buildSearchableContent();

        return $components->filter(function (BladeComponent $component) use ($searchableContent) {
            return ! $this->hasReference($component, $searchableContent);
        })->values();
    }

    /**
     * Find all files where a component is referenced.
     *
     * @return Collection<int, string>
     */
    public function findReferences(BladeComponent $component): Collection
    {
        $references = collect();

        foreach ($this->getSearchableFiles() as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            if ($this->contentReferencesComponent($content, $component)) {
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
     * Check if a component is referenced in content.
     */
    private function hasReference(BladeComponent $component, string $content): bool
    {
        return $this->contentReferencesComponent($content, $component);
    }

    /**
     * Check if content references a component using multiple strategies.
     */
    private function contentReferencesComponent(string $content, BladeComponent $component): bool
    {
        $searches = $this->getSearchPatterns($component);

        foreach ($searches as $search) {
            if (stripos($content, $search) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate search patterns for a component.
     *
     * @return array<int, string>
     */
    private function getSearchPatterns(BladeComponent $component): array
    {
        $patterns = [];
        $name = $component->name;

        // 1. Basic component tag: <x-component-name
        $patterns[] = '<x-'.$name;

        // 2. Self-closing and regular tags are covered by pattern 1

        // 3. Alternative dot notation for nested: already covered by pattern 1

        // 4. Dynamic component with static string
        $patterns[] = 'component="'.$name.'"';
        $patterns[] = "component='".$name."'";
        $patterns[] = ':component="\''.$name.'\'"';
        $patterns[] = ":component=\"'".$name."'\"";

        // 5. Legacy @component directive
        $viewName = $component->getViewName();
        $patterns[] = "@component('".$viewName."'";
        $patterns[] = '@component("'.$viewName.'"';

        // Also try with just the component name (if registered as alias)
        $patterns[] = "@component('".$name."'";
        $patterns[] = '@component("'.$name.'"';

        // 6. Blade::component alias registration (for class-based components)
        if ($component->isClassBased && $component->className) {
            // Full class name
            $patterns[] = $component->className.'::class';

            // Short class name
            $shortName = $this->getShortClassName($component->className);
            if ($shortName !== $component->className) {
                $patterns[] = $shortName.'::class';
            }
        }

        // 7. Direct view() call
        $patterns[] = "view('".$viewName."'";
        $patterns[] = 'view("'.$viewName.'"';

        // Also check for components.name format
        $patterns[] = "view('components.".$name."'";
        $patterns[] = 'view("components.'.$name.'"';

        // 8. @include directive (sometimes used for components)
        $patterns[] = "@include('".$viewName."'";
        $patterns[] = '@include("'.$viewName.'"';
        $patterns[] = "@include('components.".$name."'";
        $patterns[] = '@include("components.'.$name.'"';

        // 9. Check for the view path if available
        if ($component->viewRelativePath) {
            $viewPath = str_replace(['resources/views/', '.blade.php'], '', $component->viewRelativePath);
            $viewPath = str_replace(DIRECTORY_SEPARATOR, '.', $viewPath);
            if ($viewPath !== $viewName && $viewPath !== 'components.'.$name) {
                $patterns[] = "view('".$viewPath."'";
                $patterns[] = 'view("'.$viewPath.'"';
            }
        }

        return array_unique($patterns);
    }

    /**
     * Get the short class name from a fully qualified class name.
     */
    private function getShortClassName(string $className): string
    {
        $parts = explode('\\', $className);

        return end($parts);
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
