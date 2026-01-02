<?php

declare(strict_types=1);

use Daikazu\AssetCleaner\DTOs\ImageAsset;
use Daikazu\AssetCleaner\Services\AssetScanner;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Create a temporary directory structure for testing
    $this->tempDir = sys_get_temp_dir().'/asset-cleaner-test-'.uniqid();
    File::makeDirectory($this->tempDir.'/public/images', 0755, true);
    File::makeDirectory($this->tempDir.'/resources/images', 0755, true);

    // Create test images
    File::put($this->tempDir.'/public/images/logo.png', 'fake-png-content');
    File::put($this->tempDir.'/public/images/hero.jpg', 'fake-jpg-content');
    File::put($this->tempDir.'/resources/images/icon.svg', 'fake-svg-content');
});

afterEach(function () {
    File::deleteDirectory($this->tempDir);
});

test('it scans directories for image files', function () {
    $scanner = new AssetScanner(
        scanPaths: ['public', 'resources'],
        imageExtensions: ['png', 'jpg', 'svg'],
        excludePatterns: [],
        protectedPatterns: [],
        basePath: $this->tempDir,
    );

    $assets = $scanner->scan();

    expect($assets)->toHaveCount(3);
    expect($assets->pluck('filename')->all())->toContain('logo.png', 'hero.jpg', 'icon.svg');
});

test('it only scans configured directories', function () {
    $scanner = new AssetScanner(
        scanPaths: ['public'],
        imageExtensions: ['png', 'jpg', 'svg'],
        excludePatterns: [],
        protectedPatterns: [],
        basePath: $this->tempDir,
    );

    $assets = $scanner->scan();

    expect($assets)->toHaveCount(2);
    expect($assets->pluck('filename')->all())->not->toContain('icon.svg');
});

test('it only scans configured image extensions', function () {
    $scanner = new AssetScanner(
        scanPaths: ['public', 'resources'],
        imageExtensions: ['png'],
        excludePatterns: [],
        protectedPatterns: [],
        basePath: $this->tempDir,
    );

    $assets = $scanner->scan();

    expect($assets)->toHaveCount(1);
    expect($assets->first()->filename)->toBe('logo.png');
});

test('it excludes files matching exclude patterns', function () {
    File::makeDirectory($this->tempDir.'/public/cache', 0755, true);
    File::put($this->tempDir.'/public/cache/cached.png', 'fake-content');

    $scanner = new AssetScanner(
        scanPaths: ['public'],
        imageExtensions: ['png', 'jpg'],
        excludePatterns: ['**/cache/**'],
        protectedPatterns: [],
        basePath: $this->tempDir,
    );

    $assets = $scanner->scan();

    expect($assets)->toHaveCount(2);
    expect($assets->pluck('filename')->all())->not->toContain('cached.png');
});

test('it identifies protected assets', function () {
    $scanner = new AssetScanner(
        scanPaths: ['public'],
        imageExtensions: ['png', 'jpg'],
        excludePatterns: [],
        protectedPatterns: ['**/logo.*'],
        basePath: $this->tempDir,
    );

    $assets = $scanner->scan();
    $logoAsset = $assets->first(fn (ImageAsset $a) => $a->filename === 'logo.png');

    expect($scanner->isProtected($logoAsset))->toBeTrue();
});

test('it creates ImageAsset DTOs with correct data', function () {
    $scanner = new AssetScanner(
        scanPaths: ['public'],
        imageExtensions: ['png'],
        excludePatterns: [],
        protectedPatterns: [],
        basePath: $this->tempDir,
    );

    $assets = $scanner->scan();
    $asset = $assets->first();

    expect($asset)->toBeInstanceOf(ImageAsset::class);
    expect($asset->filename)->toBe('logo.png');
    expect($asset->extension)->toBe('png');
    expect($asset->relativePath)->toBe('public/images/logo.png');
    expect($asset->size)->toBeGreaterThan(0);
});
