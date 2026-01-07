<?php

declare(strict_types=1);

use Daikazu\AssetCleaner\DTOs\BladeComponent;
use Daikazu\AssetCleaner\Services\ComponentReferenceSearcher;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/component-ref-test-'.uniqid();
    mkdir($this->tempDir.'/resources/views/pages', 0755, true);
    mkdir($this->tempDir.'/resources/views/components', 0755, true);
    mkdir($this->tempDir.'/app/Http/Controllers', 0755, true);
    mkdir($this->tempDir.'/config', 0755, true);
});

afterEach(function () {
    deleteDirectoryRefSearcher($this->tempDir);
});

function deleteDirectoryRefSearcher(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir.'/'.$file;
        is_dir($path) ? deleteDirectoryRefSearcher($path) : unlink($path);
    }
    rmdir($dir);
}

function createComponentForTest(string $name, string $tempDir, bool $isClassBased = false): BladeComponent
{
    $viewPath = $tempDir.'/resources/views/components/'.str_replace('.', '/', $name).'.blade.php';

    if ($isClassBased) {
        return BladeComponent::classBased(
            name: $name,
            classPath: $tempDir.'/app/View/Components/'.ucfirst($name).'.php',
            className: 'App\\View\\Components\\'.ucfirst($name),
            basePath: $tempDir,
            viewPath: $viewPath,
        );
    }

    return BladeComponent::anonymous(
        name: $name,
        viewPath: $viewPath,
        basePath: $tempDir,
    );
}

test('it finds references using x-tag syntax', function () {
    file_put_contents(
        $this->tempDir.'/resources/views/components/button.blade.php',
        '<button>{{ $slot }}</button>'
    );
    file_put_contents(
        $this->tempDir.'/resources/views/pages/home.blade.php',
        '<div><x-button>Click me</x-button></div>'
    );

    $searcher = new ComponentReferenceSearcher(
        searchPaths: ['resources/views'],
        searchExtensions: ['blade.php'],
        excludePatterns: [],
        basePath: $this->tempDir,
    );

    $component = createComponentForTest('button', $this->tempDir);
    $references = $searcher->findReferences($component);

    expect($references)->toContain('resources/views/pages/home.blade.php');
});

test('it finds references using self-closing x-tag syntax', function () {
    file_put_contents(
        $this->tempDir.'/resources/views/components/icon.blade.php',
        '<svg></svg>'
    );
    file_put_contents(
        $this->tempDir.'/resources/views/pages/home.blade.php',
        '<div><x-icon /></div>'
    );

    $searcher = new ComponentReferenceSearcher(
        searchPaths: ['resources/views'],
        searchExtensions: ['blade.php'],
        excludePatterns: [],
        basePath: $this->tempDir,
    );

    $component = createComponentForTest('icon', $this->tempDir);
    $references = $searcher->findReferences($component);

    expect($references)->toContain('resources/views/pages/home.blade.php');
});

test('it finds references using nested component names', function () {
    mkdir($this->tempDir.'/resources/views/components/forms', 0755, true);
    file_put_contents(
        $this->tempDir.'/resources/views/components/forms/input.blade.php',
        '<input />'
    );
    file_put_contents(
        $this->tempDir.'/resources/views/pages/form.blade.php',
        '<form><x-forms.input name="email" /></form>'
    );

    $searcher = new ComponentReferenceSearcher(
        searchPaths: ['resources/views'],
        searchExtensions: ['blade.php'],
        excludePatterns: [],
        basePath: $this->tempDir,
    );

    $component = createComponentForTest('forms.input', $this->tempDir);
    $references = $searcher->findReferences($component);

    expect($references)->toContain('resources/views/pages/form.blade.php');
});

test('it finds references using @component directive', function () {
    file_put_contents(
        $this->tempDir.'/resources/views/components/alert.blade.php',
        '<div class="alert">{{ $slot }}</div>'
    );
    file_put_contents(
        $this->tempDir.'/resources/views/pages/dashboard.blade.php',
        "@component('components.alert')Warning!@endcomponent"
    );

    $searcher = new ComponentReferenceSearcher(
        searchPaths: ['resources/views'],
        searchExtensions: ['blade.php'],
        excludePatterns: [],
        basePath: $this->tempDir,
    );

    $component = createComponentForTest('alert', $this->tempDir);
    $references = $searcher->findReferences($component);

    expect($references)->toContain('resources/views/pages/dashboard.blade.php');
});

test('it finds references using dynamic component with static string', function () {
    file_put_contents(
        $this->tempDir.'/resources/views/components/modal.blade.php',
        '<div class="modal">{{ $slot }}</div>'
    );
    file_put_contents(
        $this->tempDir.'/resources/views/pages/page.blade.php',
        '<x-dynamic-component component="modal" />'
    );

    $searcher = new ComponentReferenceSearcher(
        searchPaths: ['resources/views'],
        searchExtensions: ['blade.php'],
        excludePatterns: [],
        basePath: $this->tempDir,
    );

    $component = createComponentForTest('modal', $this->tempDir);
    $references = $searcher->findReferences($component);

    expect($references)->toContain('resources/views/pages/page.blade.php');
});

test('it finds unused components', function () {
    file_put_contents(
        $this->tempDir.'/resources/views/components/used.blade.php',
        '<div>Used</div>'
    );
    file_put_contents(
        $this->tempDir.'/resources/views/components/unused.blade.php',
        '<div>Unused</div>'
    );
    file_put_contents(
        $this->tempDir.'/resources/views/pages/home.blade.php',
        '<x-used />'
    );

    $searcher = new ComponentReferenceSearcher(
        searchPaths: ['resources/views'],
        searchExtensions: ['blade.php'],
        excludePatterns: [],
        basePath: $this->tempDir,
    );

    $components = collect([
        createComponentForTest('used', $this->tempDir),
        createComponentForTest('unused', $this->tempDir),
    ]);

    $unused = $searcher->findUnusedComponents($components);

    expect($unused)->toHaveCount(1);
    expect($unused->first()->name)->toBe('unused');
});

test('it searches only configured file extensions', function () {
    file_put_contents(
        $this->tempDir.'/resources/views/components/button.blade.php',
        '<button>Click</button>'
    );
    file_put_contents(
        $this->tempDir.'/resources/views/pages/page.blade.php',
        '<x-button />'
    );
    // Reference in .txt file should be ignored
    file_put_contents(
        $this->tempDir.'/resources/views/pages/notes.txt',
        '<x-button />'
    );

    $searcher = new ComponentReferenceSearcher(
        searchPaths: ['resources/views'],
        searchExtensions: ['blade.php'],
        excludePatterns: [],
        basePath: $this->tempDir,
    );

    $component = createComponentForTest('button', $this->tempDir);
    $references = $searcher->findReferences($component);

    expect($references)->toContain('resources/views/pages/page.blade.php');
    expect($references)->not->toContain('resources/views/pages/notes.txt');
});

test('it excludes paths matching exclude patterns', function () {
    mkdir($this->tempDir.'/resources/views/vendor', 0755, true);
    file_put_contents(
        $this->tempDir.'/resources/views/components/button.blade.php',
        '<button>Click</button>'
    );
    file_put_contents(
        $this->tempDir.'/resources/views/vendor/package.blade.php',
        '<x-button />'
    );

    $searcher = new ComponentReferenceSearcher(
        searchPaths: ['resources/views'],
        searchExtensions: ['blade.php'],
        excludePatterns: ['**/vendor/**'],
        basePath: $this->tempDir,
    );

    $component = createComponentForTest('button', $this->tempDir);
    $references = $searcher->findReferences($component);

    expect($references)->not->toContain('resources/views/vendor/package.blade.php');
});

test('it finds references in PHP files', function () {
    file_put_contents(
        $this->tempDir.'/resources/views/components/card.blade.php',
        '<div class="card">{{ $slot }}</div>'
    );
    file_put_contents(
        $this->tempDir.'/app/Http/Controllers/HomeController.php',
        "<?php return view('components.card');"
    );

    $searcher = new ComponentReferenceSearcher(
        searchPaths: ['app', 'resources/views'],
        searchExtensions: ['php', 'blade.php'],
        excludePatterns: [],
        basePath: $this->tempDir,
    );

    $component = createComponentForTest('card', $this->tempDir);
    $references = $searcher->findReferences($component);

    expect($references)->toContain('app/Http/Controllers/HomeController.php');
});
