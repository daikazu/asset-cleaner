<?php

declare(strict_types=1);

use Daikazu\AssetCleaner\DTOs\ImageAsset;
use Daikazu\AssetCleaner\Services\ManifestManager;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/asset-cleaner-manifest-test-'.uniqid();
    mkdir($this->tempDir, 0755, true);
});

afterEach(function () {
    deleteDirectoryManifest($this->tempDir);
});

function deleteDirectoryManifest(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir.'/'.$file;
        is_dir($path) ? deleteDirectoryManifest($path) : unlink($path);
    }
    rmdir($dir);
}

test('it generates a manifest file', function () {
    $manager = new ManifestManager(
        manifestPath: 'unused-assets.json',
        basePath: $this->tempDir,
    );

    file_put_contents($this->tempDir.'/test.png', 'fake-content');
    $assets = collect([
        ImageAsset::fromPath($this->tempDir.'/test.png', $this->tempDir),
    ]);

    $manager->generate($assets, 5);

    expect($manager->exists())->toBeTrue();

    $manifest = $manager->load();
    expect($manifest)->toBeArray();
    expect($manifest['total_scanned'])->toBe(5);
    expect($manifest['total_unused'])->toBe(1);
    expect($manifest['assets'])->toHaveCount(1);
});

test('it loads assets from manifest', function () {
    $manager = new ManifestManager(
        manifestPath: 'unused-assets.json',
        basePath: $this->tempDir,
    );

    file_put_contents($this->tempDir.'/test.png', 'fake-content');
    $originalAssets = collect([
        ImageAsset::fromPath($this->tempDir.'/test.png', $this->tempDir),
    ]);

    $manager->generate($originalAssets, 1);

    $loadedAssets = $manager->getAssets();

    expect($loadedAssets)->toHaveCount(1);
    expect($loadedAssets->first()->filename)->toBe('test.png');
});

test('it returns empty collection when no manifest exists', function () {
    $manager = new ManifestManager(
        manifestPath: 'unused-assets.json',
        basePath: $this->tempDir,
    );

    expect($manager->exists())->toBeFalse();
    expect($manager->load())->toBeNull();
    expect($manager->getAssets())->toBeEmpty();
});

test('it deletes the manifest file', function () {
    $manager = new ManifestManager(
        manifestPath: 'unused-assets.json',
        basePath: $this->tempDir,
    );

    file_put_contents($this->tempDir.'/test.png', 'fake-content');
    $assets = collect([
        ImageAsset::fromPath($this->tempDir.'/test.png', $this->tempDir),
    ]);

    $manager->generate($assets, 1);
    expect($manager->exists())->toBeTrue();

    $manager->delete();
    expect($manager->exists())->toBeFalse();
});

test('it includes instructions in manifest', function () {
    $manager = new ManifestManager(
        manifestPath: 'unused-assets.json',
        basePath: $this->tempDir,
    );

    $manager->generate(collect(), 0);
    $manifest = $manager->load();

    expect($manifest['instructions'])->toBeArray();
    expect($manifest['instructions'])->toHaveKey('review');
    expect($manifest['instructions'])->toHaveKey('clean');
});
