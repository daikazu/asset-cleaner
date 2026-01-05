<?php

declare(strict_types=1);

namespace Daikazu\AssetCleaner\PatternGenerators;

use Daikazu\AssetCleaner\Contracts\PatternGenerator;
use Daikazu\AssetCleaner\DTOs\ImageAsset;

final class BladeIconsPatternGenerator implements PatternGenerator
{
    /**
     * Check if this generator supports the given asset.
     * Only supports SVG files.
     */
    public function supports(ImageAsset $asset): bool
    {
        return strtolower(pathinfo($asset->filename, PATHINFO_EXTENSION)) === 'svg';
    }

    /**
     * Generate Blade Icons component patterns for SVG files.
     *
     * @return array<int, string>
     */
    public function generate(ImageAsset $asset): array
    {
        $patterns = [];

        $filenameWithoutExtension = pathinfo($asset->filename, PATHINFO_FILENAME);
        $kebabName = $this->toKebabCase($filenameWithoutExtension);

        // Only add if different from the original
        if ($kebabName !== $filenameWithoutExtension && $kebabName !== strtolower($filenameWithoutExtension)) {
            $patterns[] = $kebabName;
        }

        return $patterns;
    }

    /**
     * Check if Blade Icons package is installed.
     */
    public static function isAvailable(): bool
    {
        return class_exists(\BladeUI\Icons\BladeIconsServiceProvider::class);
    }

    /**
     * Convert a string to kebab-case.
     *
     * Handles: PascalCase, camelCase, snake_case, and mixed formats.
     */
    private function toKebabCase(string $string): string
    {
        // Replace underscores and spaces with hyphens
        $string = str_replace(['_', ' '], '-', $string);

        // Insert hyphens before uppercase letters (for PascalCase/camelCase)
        $string = (string) preg_replace('/([a-z])([A-Z])/', '$1-$2', $string);

        // Convert to lowercase
        $string = strtolower($string);

        // Remove any duplicate hyphens
        $string = (string) preg_replace('/-+/', '-', $string);

        // Trim hyphens from start and end
        return trim($string, '-');
    }
}
