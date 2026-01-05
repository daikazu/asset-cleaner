<?php

declare(strict_types=1);

namespace Daikazu\AssetCleaner\Contracts;

use Daikazu\AssetCleaner\DTOs\ImageAsset;

interface PatternGenerator
{
    /**
     * Check if this generator supports the given asset.
     */
    public function supports(ImageAsset $asset): bool;

    /**
     * Generate additional search patterns for the asset.
     *
     * @return array<int, string>
     */
    public function generate(ImageAsset $asset): array;

    /**
     * Check if the package this generator supports is available.
     */
    public static function isAvailable(): bool;
}
