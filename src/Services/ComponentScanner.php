<?php

declare(strict_types=1);

namespace Daikazu\AssetCleaner\Services;

use Daikazu\AssetCleaner\DTOs\BladeComponent;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

final class ComponentScanner
{
    /**
     * @param  array<int, string>  $anonymousPaths
     * @param  array<int, string>  $classPaths
     * @param  array<int, string>  $excludePatterns
     * @param  array<int, string>  $protectedPatterns
     */
    public function __construct(
        private readonly array $anonymousPaths,
        private readonly array $classPaths,
        private readonly array $excludePatterns,
        private readonly array $protectedPatterns,
        private readonly string $basePath,
    ) {}

    /**
     * Scan for all Blade components (anonymous and class-based).
     *
     * @return Collection<int, BladeComponent>
     */
    public function scan(): Collection
    {
        $components = collect();

        // First, scan for class-based components
        $classBasedComponents = $this->scanClassBasedComponents();
        $classBasedViewPaths = $classBasedComponents
            ->filter(fn (BladeComponent $c) => $c->viewPath !== null)
            ->pluck('viewPath')
            ->toArray();

        // Then scan for anonymous components, excluding those that belong to class-based components
        $anonymousComponents = $this->scanAnonymousComponents($classBasedViewPaths);

        return $components
            ->merge($classBasedComponents)
            ->merge($anonymousComponents)
            ->values();
    }

    /**
     * Scan for anonymous (file-based) components.
     *
     * @param  array<string>  $excludeViewPaths  View paths that belong to class-based components
     * @return Collection<int, BladeComponent>
     */
    private function scanAnonymousComponents(array $excludeViewPaths = []): Collection
    {
        $components = collect();

        foreach ($this->anonymousPaths as $path) {
            $fullPath = $this->basePath.DIRECTORY_SEPARATOR.$path;

            if (! is_dir($fullPath)) {
                continue;
            }

            $components = $components->merge(
                $this->scanAnonymousDirectory($fullPath, $path, $excludeViewPaths)
            );
        }

        return $components;
    }

    /**
     * Scan a directory for anonymous Blade components.
     *
     * @param  array<string>  $excludeViewPaths
     * @return Collection<int, BladeComponent>
     */
    private function scanAnonymousDirectory(string $directory, string $relativePath, array $excludeViewPaths): Collection
    {
        $components = collect();

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

            if (! str_ends_with($path, '.blade.php')) {
                continue;
            }

            if ($this->isExcluded($path)) {
                continue;
            }

            // Skip views that belong to class-based components
            if (in_array($path, $excludeViewPaths, true)) {
                continue;
            }

            $componentName = $this->deriveAnonymousComponentName($path, $directory);
            $component = BladeComponent::anonymous($componentName, $path, $this->basePath);

            $components->push($component);
        }

        return $components;
    }

    /**
     * Scan for class-based components.
     *
     * @return Collection<int, BladeComponent>
     */
    private function scanClassBasedComponents(): Collection
    {
        $components = collect();

        foreach ($this->classPaths as $path) {
            $fullPath = $this->basePath.DIRECTORY_SEPARATOR.$path;

            if (! is_dir($fullPath)) {
                continue;
            }

            $components = $components->merge(
                $this->scanClassDirectory($fullPath, $path)
            );
        }

        return $components;
    }

    /**
     * Scan a directory for class-based Blade components.
     *
     * @return Collection<int, BladeComponent>
     */
    private function scanClassDirectory(string $directory, string $relativePath): Collection
    {
        $components = collect();

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

            if (! str_ends_with($path, '.php')) {
                continue;
            }

            if ($this->isExcluded($path)) {
                continue;
            }

            $className = $this->getClassNameFromFile($path);

            if ($className === null) {
                continue;
            }

            // Skip if not a Blade component class
            if (! $this->isBladeComponentClass($className)) {
                continue;
            }

            $componentName = $this->deriveClassBasedComponentName($className);
            $viewPath = $this->findViewForClass($className, $path);

            $component = BladeComponent::classBased(
                name: $componentName,
                classPath: $path,
                className: $className,
                basePath: $this->basePath,
                viewPath: $viewPath,
            );

            $components->push($component);
        }

        return $components;
    }

    /**
     * Derive component name from anonymous component file path.
     * Example: resources/views/components/forms/input.blade.php -> forms.input
     */
    private function deriveAnonymousComponentName(string $filePath, string $componentsDirectory): string
    {
        // Get path relative to components directory
        $relativePath = str_replace($componentsDirectory.DIRECTORY_SEPARATOR, '', $filePath);

        // Remove .blade.php extension
        $name = preg_replace('/\.blade\.php$/', '', $relativePath);

        // Replace directory separators with dots
        $name = str_replace(DIRECTORY_SEPARATOR, '.', $name);

        // Handle index.blade.php files (card/index.blade.php -> card)
        if (str_ends_with($name, '.index')) {
            $name = substr($name, 0, -6);
        }

        return $name;
    }

    /**
     * Derive component name from class name.
     * Example: App\View\Components\Forms\TextInput -> forms.text-input
     */
    private function deriveClassBasedComponentName(string $className): string
    {
        // Remove the base namespace
        $name = preg_replace('/^App\\\\View\\\\Components\\\\/', '', $className);

        // Split by namespace separator
        $parts = explode('\\', $name);

        // Convert each part to kebab-case
        $kebabParts = array_map(fn ($part) => $this->toKebabCase($part), $parts);

        return implode('.', $kebabParts);
    }

    /**
     * Convert a string to kebab-case.
     */
    private function toKebabCase(string $string): string
    {
        // Handle PascalCase and camelCase
        $result = preg_replace('/([a-z])([A-Z])/', '$1-$2', $string);
        // Handle sequences of uppercase letters followed by lowercase
        $result = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1-$2', $result);
        // Handle underscores
        $result = str_replace('_', '-', $result);
        // Convert to lowercase
        $result = strtolower($result);
        // Remove any double dashes
        $result = preg_replace('/-+/', '-', $result);

        return trim($result, '-');
    }

    /**
     * Get the fully qualified class name from a PHP file.
     */
    private function getClassNameFromFile(string $filePath): ?string
    {
        $content = file_get_contents($filePath);

        if ($content === false) {
            return null;
        }

        // Extract namespace
        $namespace = null;
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = trim($matches[1]);
        }

        // Extract class name
        $className = null;
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            $className = trim($matches[1]);
        }

        if ($className === null) {
            return null;
        }

        return $namespace ? $namespace.'\\'.$className : $className;
    }

    /**
     * Check if a class extends Illuminate\View\Component.
     */
    private function isBladeComponentClass(string $className): bool
    {
        if (! class_exists($className)) {
            return false;
        }

        try {
            $reflection = new ReflectionClass($className);

            return $reflection->isSubclassOf(Component::class);
        } catch (\ReflectionException) {
            return false;
        }
    }

    /**
     * Find the view file associated with a class-based component.
     */
    private function findViewForClass(string $className, string $classPath): ?string
    {
        // First, try to parse the render() method for a custom view path
        $customView = $this->parseRenderMethod($classPath);

        if ($customView !== null) {
            // Convert view name to file path
            // components.forms.input -> resources/views/components/forms/input.blade.php
            $viewFile = str_replace('.', DIRECTORY_SEPARATOR, $customView).'.blade.php';
            $fullPath = $this->basePath.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.$viewFile;

            if (file_exists($fullPath)) {
                return $fullPath;
            }
        }

        // Fall back to Laravel convention
        $componentName = $this->deriveClassBasedComponentName($className);
        $viewFile = str_replace('.', DIRECTORY_SEPARATOR, $componentName).'.blade.php';
        $conventionPath = $this->basePath.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.$viewFile;

        if (file_exists($conventionPath)) {
            return $conventionPath;
        }

        // Check for index.blade.php variant
        $indexPath = $this->basePath.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.str_replace('.', DIRECTORY_SEPARATOR, $componentName).DIRECTORY_SEPARATOR.'index.blade.php';

        if (file_exists($indexPath)) {
            return $indexPath;
        }

        // No view file found (inline component)
        return null;
    }

    /**
     * Parse the render() method to find custom view path.
     */
    private function parseRenderMethod(string $classPath): ?string
    {
        $content = file_get_contents($classPath);

        if ($content === false) {
            return null;
        }

        // Match: return view('components.forms.custom-input')
        // or: return view("components.forms.custom-input")
        if (preg_match("/return\s+view\s*\(\s*['\"]([^'\"]+)['\"]/", $content, $matches)) {
            return $matches[1];
        }

        return null;
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
     * Check if a component is protected from deletion.
     */
    public function isProtected(BladeComponent $component): bool
    {
        foreach ($this->protectedPatterns as $pattern) {
            if ($this->matchesGlobPattern($component->name, $pattern)) {
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
