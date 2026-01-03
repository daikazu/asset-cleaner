<?php

declare(strict_types=1);

use Daikazu\AssetCleaner\DTOs\ImageAsset;
use Daikazu\AssetCleaner\Services\ReferenceSearcher;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/asset-cleaner-ref-test-'.uniqid();
    mkdir($this->tempDir.'/public/images', 0755, true);
    mkdir($this->tempDir.'/resources/views', 0755, true);
    mkdir($this->tempDir.'/app', 0755, true);

    // Create test images
    file_put_contents($this->tempDir.'/public/images/hero-banner.png', 'fake-content');
    file_put_contents($this->tempDir.'/public/images/orphan-image.png', 'fake-content');

    // Create files that reference the image
    file_put_contents($this->tempDir.'/resources/views/home.blade.php', '<img src="/images/hero-banner.png">');
    file_put_contents($this->tempDir.'/app/Controller.php', '<?php $image = "some-other-image.png";');
});

afterEach(function () {
    deleteDirectory($this->tempDir);
});

function deleteDirectory(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir.'/'.$file;
        is_dir($path) ? deleteDirectory($path) : unlink($path);
    }
    rmdir($dir);
}

test('it finds unused assets', function () {
    $searcher = new ReferenceSearcher(
        searchPaths: ['resources', 'app'],
        searchExtensions: ['blade.php', 'php'],
        excludePatterns: [],
        basePath: $this->tempDir,
    );

    $assets = collect([
        ImageAsset::fromPath($this->tempDir.'/public/images/hero-banner.png', $this->tempDir),
        ImageAsset::fromPath($this->tempDir.'/public/images/orphan-image.png', $this->tempDir),
    ]);

    $unused = $searcher->findUnusedAssets($assets);

    expect($unused)->toHaveCount(1);
    expect($unused->first()->filename)->toBe('orphan-image.png');
});

test('it finds references using full path', function () {
    $searcher = new ReferenceSearcher(
        searchPaths: ['resources'],
        searchExtensions: ['blade.php'],
        excludePatterns: [],
        basePath: $this->tempDir,
    );

    $asset = ImageAsset::fromPath($this->tempDir.'/public/images/hero-banner.png', $this->tempDir);
    $references = $searcher->findReferences($asset);

    expect($references)->toHaveCount(1);
    expect($references->first())->toContain('home.blade.php');
});

test('it finds references using filename only', function () {
    file_put_contents($this->tempDir.'/resources/views/test.blade.php', '<img src="{{ asset("logo.png") }}">');
    file_put_contents($this->tempDir.'/public/images/logo.png', 'fake-content');

    $searcher = new ReferenceSearcher(
        searchPaths: ['resources'],
        searchExtensions: ['blade.php'],
        excludePatterns: [],
        basePath: $this->tempDir,
    );

    $asset = ImageAsset::fromPath($this->tempDir.'/public/images/logo.png', $this->tempDir);
    $references = $searcher->findReferences($asset);

    expect($references)->toHaveCount(1);
});

test('it searches only configured file extensions', function () {
    // Create a .txt file with a reference - should NOT be searched
    file_put_contents($this->tempDir.'/app/notes.txt', 'orphan-image.png');

    $searcher = new ReferenceSearcher(
        searchPaths: ['app'],
        searchExtensions: ['php'], // Only PHP, not txt
        excludePatterns: [],
        basePath: $this->tempDir,
    );

    $asset = ImageAsset::fromPath($this->tempDir.'/public/images/orphan-image.png', $this->tempDir);
    $references = $searcher->findReferences($asset);

    // The Controller.php doesn't reference orphan-image.png, only some-other-image.png
    expect($references)->toHaveCount(0);
});

test('it excludes paths matching exclude patterns', function () {
    mkdir($this->tempDir.'/vendor', 0755, true);
    file_put_contents($this->tempDir.'/vendor/package.php', 'orphan-image.png');

    $searcher = new ReferenceSearcher(
        searchPaths: ['vendor'],
        searchExtensions: ['php'],
        excludePatterns: ['**/vendor/**'],
        basePath: $this->tempDir,
    );

    $asset = ImageAsset::fromPath($this->tempDir.'/public/images/orphan-image.png', $this->tempDir);
    $references = $searcher->findReferences($asset);

    expect($references)->toHaveCount(0);
});

test('it finds no references for truly orphaned assets', function () {
    $searcher = new ReferenceSearcher(
        searchPaths: ['resources', 'app'],
        searchExtensions: ['blade.php', 'php'],
        excludePatterns: [],
        basePath: $this->tempDir,
    );

    $asset = ImageAsset::fromPath($this->tempDir.'/public/images/orphan-image.png', $this->tempDir);
    $references = $searcher->findReferences($asset);

    expect($references)->toHaveCount(0);
});
