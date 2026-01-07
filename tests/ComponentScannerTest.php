<?php

declare(strict_types=1);

use Daikazu\AssetCleaner\DTOs\BladeComponent;
use Daikazu\AssetCleaner\Services\ComponentScanner;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/component-scanner-test-'.uniqid();
    mkdir($this->tempDir.'/resources/views/components/forms', 0755, true);
    mkdir($this->tempDir.'/app/View/Components', 0755, true);

    // Create anonymous components
    file_put_contents(
        $this->tempDir.'/resources/views/components/button.blade.php',
        '<button>{{ $slot }}</button>'
    );
    file_put_contents(
        $this->tempDir.'/resources/views/components/forms/input.blade.php',
        '<input type="text" />'
    );
});

afterEach(function () {
    deleteDirectoryComponentScanner($this->tempDir);
});

function deleteDirectoryComponentScanner(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir.'/'.$file;
        is_dir($path) ? deleteDirectoryComponentScanner($path) : unlink($path);
    }
    rmdir($dir);
}

test('it scans for anonymous blade components', function () {
    $scanner = new ComponentScanner(
        anonymousPaths: ['resources/views/components'],
        classPaths: [],
        excludePatterns: [],
        protectedPatterns: [],
        basePath: $this->tempDir,
    );

    $components = $scanner->scan();

    expect($components)->toHaveCount(2);
    expect($components->pluck('name')->all())->toContain('button', 'forms.input');
});

test('it derives nested component names correctly', function () {
    $scanner = new ComponentScanner(
        anonymousPaths: ['resources/views/components'],
        classPaths: [],
        excludePatterns: [],
        protectedPatterns: [],
        basePath: $this->tempDir,
    );

    $components = $scanner->scan();
    $formsInput = $components->first(fn (BladeComponent $c) => $c->name === 'forms.input');

    expect($formsInput)->not->toBeNull();
    expect($formsInput->getTagName())->toBe('x-forms.input');
});

test('it handles index.blade.php as directory component', function () {
    mkdir($this->tempDir.'/resources/views/components/card', 0755, true);
    file_put_contents(
        $this->tempDir.'/resources/views/components/card/index.blade.php',
        '<div class="card">{{ $slot }}</div>'
    );

    $scanner = new ComponentScanner(
        anonymousPaths: ['resources/views/components'],
        classPaths: [],
        excludePatterns: [],
        protectedPatterns: [],
        basePath: $this->tempDir,
    );

    $components = $scanner->scan();
    $card = $components->first(fn (BladeComponent $c) => $c->name === 'card');

    expect($card)->not->toBeNull();
    expect($card->getTagName())->toBe('x-card');
});

test('it excludes paths matching exclude patterns', function () {
    mkdir($this->tempDir.'/resources/views/components/vendor', 0755, true);
    file_put_contents(
        $this->tempDir.'/resources/views/components/vendor/external.blade.php',
        '<div>External</div>'
    );

    $scanner = new ComponentScanner(
        anonymousPaths: ['resources/views/components'],
        classPaths: [],
        excludePatterns: ['**/vendor/**'],
        protectedPatterns: [],
        basePath: $this->tempDir,
    );

    $components = $scanner->scan();

    expect($components->pluck('name')->all())->not->toContain('vendor.external');
});

test('it identifies protected components', function () {
    $scanner = new ComponentScanner(
        anonymousPaths: ['resources/views/components'],
        classPaths: [],
        excludePatterns: [],
        protectedPatterns: ['button', 'layouts.*'],
        basePath: $this->tempDir,
    );

    $components = $scanner->scan();
    $button = $components->first(fn (BladeComponent $c) => $c->name === 'button');

    expect($scanner->isProtected($button))->toBeTrue();
});

test('it only scans configured directories', function () {
    mkdir($this->tempDir.'/other/components', 0755, true);
    file_put_contents(
        $this->tempDir.'/other/components/other.blade.php',
        '<div>Other</div>'
    );

    $scanner = new ComponentScanner(
        anonymousPaths: ['resources/views/components'],
        classPaths: [],
        excludePatterns: [],
        protectedPatterns: [],
        basePath: $this->tempDir,
    );

    $components = $scanner->scan();

    expect($components->pluck('name')->all())->not->toContain('other');
});

test('it creates BladeComponent DTOs with correct data', function () {
    $scanner = new ComponentScanner(
        anonymousPaths: ['resources/views/components'],
        classPaths: [],
        excludePatterns: [],
        protectedPatterns: [],
        basePath: $this->tempDir,
    );

    $components = $scanner->scan();
    $button = $components->first(fn (BladeComponent $c) => $c->name === 'button');

    expect($button)->toBeInstanceOf(BladeComponent::class);
    expect($button->name)->toBe('button');
    expect($button->isClassBased)->toBeFalse();
    expect($button->viewRelativePath)->toBe('resources/views/components/button.blade.php');
    expect($button->totalSize)->toBeGreaterThan(0);
});

test('it handles missing directories gracefully', function () {
    $scanner = new ComponentScanner(
        anonymousPaths: ['nonexistent/path'],
        classPaths: ['another/nonexistent'],
        excludePatterns: [],
        protectedPatterns: [],
        basePath: $this->tempDir,
    );

    $components = $scanner->scan();

    expect($components)->toBeEmpty();
});
