<?php

declare(strict_types=1);

use Daikazu\AssetCleaner\DTOs\BladeComponent;
use Daikazu\AssetCleaner\Services\ComponentManifestManager;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/component-manifest-test-'.uniqid();
    mkdir($this->tempDir.'/resources/views/components', 0755, true);

    // Create a test component file
    file_put_contents(
        $this->tempDir.'/resources/views/components/button.blade.php',
        '<button>{{ $slot }}</button>'
    );
});

afterEach(function () {
    deleteDirectoryComponentManifest($this->tempDir);
});

function deleteDirectoryComponentManifest(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir.'/'.$file;
        is_dir($path) ? deleteDirectoryComponentManifest($path) : unlink($path);
    }
    rmdir($dir);
}

test('it generates a manifest file', function () {
    $manager = new ComponentManifestManager(
        manifestPath: 'unused-components.json',
        basePath: $this->tempDir,
    );

    $components = collect([
        BladeComponent::anonymous(
            name: 'button',
            viewPath: $this->tempDir.'/resources/views/components/button.blade.php',
            basePath: $this->tempDir,
        ),
    ]);

    $manager->generate($components, 5);

    expect($manager->exists())->toBeTrue();

    $manifest = $manager->load();
    expect($manifest)->not->toBeNull();
    expect($manifest['total_scanned'])->toBe(5);
    expect($manifest['total_unused'])->toBe(1);
    expect($manifest['components'])->toHaveCount(1);
});

test('it includes instructions in manifest', function () {
    $manager = new ComponentManifestManager(
        manifestPath: 'unused-components.json',
        basePath: $this->tempDir,
    );

    $manager->generate(collect(), 0);

    $manifest = $manager->load();

    expect($manifest)->toHaveKey('instructions');
    expect($manifest['instructions'])->toHaveKey('review');
    expect($manifest['instructions'])->toHaveKey('delete_entry');
    expect($manifest['instructions'])->toHaveKey('clean');
});

test('it loads components from manifest', function () {
    $manager = new ComponentManifestManager(
        manifestPath: 'unused-components.json',
        basePath: $this->tempDir,
    );

    $originalComponent = BladeComponent::anonymous(
        name: 'button',
        viewPath: $this->tempDir.'/resources/views/components/button.blade.php',
        basePath: $this->tempDir,
    );

    $manager->generate(collect([$originalComponent]), 1);

    $loadedComponents = $manager->getComponents();

    expect($loadedComponents)->toHaveCount(1);
    expect($loadedComponents->first())->toBeInstanceOf(BladeComponent::class);
    expect($loadedComponents->first()->name)->toBe('button');
});

test('it returns empty collection when no manifest exists', function () {
    $manager = new ComponentManifestManager(
        manifestPath: 'nonexistent.json',
        basePath: $this->tempDir,
    );

    expect($manager->getComponents())->toBeEmpty();
});

test('it deletes the manifest file', function () {
    $manager = new ComponentManifestManager(
        manifestPath: 'unused-components.json',
        basePath: $this->tempDir,
    );

    $manager->generate(collect(), 0);
    expect($manager->exists())->toBeTrue();

    $manager->delete();
    expect($manager->exists())->toBeFalse();
});

test('it includes human readable sizes in manifest', function () {
    $manager = new ComponentManifestManager(
        manifestPath: 'unused-components.json',
        basePath: $this->tempDir,
    );

    $components = collect([
        BladeComponent::anonymous(
            name: 'button',
            viewPath: $this->tempDir.'/resources/views/components/button.blade.php',
            basePath: $this->tempDir,
        ),
    ]);

    $manager->generate($components, 1);

    $manifest = $manager->load();

    expect($manifest)->toHaveKey('total_size_human');
    expect($manifest['components'][0])->toHaveKey('size_human');
});

test('it returns correct manifest path', function () {
    $manager = new ComponentManifestManager(
        manifestPath: 'unused-components.json',
        basePath: $this->tempDir,
    );

    expect($manager->getManifestPath())->toBe('unused-components.json');
    expect($manager->getFullManifestPath())->toBe($this->tempDir.'/unused-components.json');
});
