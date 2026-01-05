<?php

declare(strict_types=1);

use Daikazu\AssetCleaner\DTOs\ImageAsset;
use Daikazu\AssetCleaner\PatternGenerators\BladeIconsPatternGenerator;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/asset-cleaner-blade-test-'.uniqid();
    mkdir($this->tempDir.'/resources/svg', 0755, true);

    $this->generator = new BladeIconsPatternGenerator;
});

afterEach(function () {
    removeTestDirectory($this->tempDir);
});

function removeTestDirectory(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir.'/'.$file;
        is_dir($path) ? removeTestDirectory($path) : unlink($path);
    }
    rmdir($dir);
}

test('it only supports SVG files', function () {
    file_put_contents($this->tempDir.'/resources/svg/icon.svg', '<svg></svg>');
    file_put_contents($this->tempDir.'/resources/svg/image.png', 'fake-content');

    $svgAsset = ImageAsset::fromPath($this->tempDir.'/resources/svg/icon.svg', $this->tempDir);
    $pngAsset = ImageAsset::fromPath($this->tempDir.'/resources/svg/image.png', $this->tempDir);

    expect($this->generator->supports($svgAsset))->toBeTrue();
    expect($this->generator->supports($pngAsset))->toBeFalse();
});

test('it generates kebab-case pattern from PascalCase filename', function () {
    file_put_contents($this->tempDir.'/resources/svg/ChevronRight.svg', '<svg></svg>');

    $asset = ImageAsset::fromPath($this->tempDir.'/resources/svg/ChevronRight.svg', $this->tempDir);
    $patterns = $this->generator->generate($asset);

    expect($patterns)->toContain('chevron-right');
});

test('it generates kebab-case pattern from camelCase filename', function () {
    file_put_contents($this->tempDir.'/resources/svg/chevronRight.svg', '<svg></svg>');

    $asset = ImageAsset::fromPath($this->tempDir.'/resources/svg/chevronRight.svg', $this->tempDir);
    $patterns = $this->generator->generate($asset);

    expect($patterns)->toContain('chevron-right');
});

test('it generates kebab-case pattern from snake_case filename', function () {
    file_put_contents($this->tempDir.'/resources/svg/chevron_right.svg', '<svg></svg>');

    $asset = ImageAsset::fromPath($this->tempDir.'/resources/svg/chevron_right.svg', $this->tempDir);
    $patterns = $this->generator->generate($asset);

    expect($patterns)->toContain('chevron-right');
});

test('it returns empty array when filename is already kebab-case', function () {
    file_put_contents($this->tempDir.'/resources/svg/chevron-right.svg', '<svg></svg>');

    $asset = ImageAsset::fromPath($this->tempDir.'/resources/svg/chevron-right.svg', $this->tempDir);
    $patterns = $this->generator->generate($asset);

    // Should not duplicate the pattern since it's already in the base patterns
    expect($patterns)->toBeEmpty();
});

test('it returns empty array for simple lowercase filename', function () {
    file_put_contents($this->tempDir.'/resources/svg/chevron.svg', '<svg></svg>');

    $asset = ImageAsset::fromPath($this->tempDir.'/resources/svg/chevron.svg', $this->tempDir);
    $patterns = $this->generator->generate($asset);

    // Simple name doesn't need a kebab variant
    expect($patterns)->toBeEmpty();
});
