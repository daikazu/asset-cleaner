<?php

declare(strict_types=1);

use Daikazu\AssetCleaner\DTOs\BladeComponent;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/blade-component-test-'.uniqid();
    mkdir($this->tempDir.'/resources/views/components', 0755, true);
    mkdir($this->tempDir.'/app/View/Components', 0755, true);

    // Create test view file
    file_put_contents($this->tempDir.'/resources/views/components/button.blade.php', '<button>{{ $slot }}</button>');
    // Create test class file
    file_put_contents($this->tempDir.'/app/View/Components/Alert.php', '<?php class Alert {}');
});

afterEach(function () {
    deleteDirectoryBladeComponent($this->tempDir);
});

function deleteDirectoryBladeComponent(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir.'/'.$file;
        is_dir($path) ? deleteDirectoryBladeComponent($path) : unlink($path);
    }
    rmdir($dir);
}

test('it creates anonymous component from view path', function () {
    $viewPath = $this->tempDir.'/resources/views/components/button.blade.php';

    $component = BladeComponent::anonymous(
        name: 'button',
        viewPath: $viewPath,
        basePath: $this->tempDir,
    );

    expect($component->name)->toBe('button');
    expect($component->isClassBased)->toBeFalse();
    expect($component->viewPath)->toBe($viewPath);
    expect($component->viewRelativePath)->toBe('resources/views/components/button.blade.php');
    expect($component->classPath)->toBeNull();
    expect($component->className)->toBeNull();
    expect($component->totalSize)->toBeGreaterThan(0);
});

test('it creates class-based component with view', function () {
    $classPath = $this->tempDir.'/app/View/Components/Alert.php';
    $viewPath = $this->tempDir.'/resources/views/components/button.blade.php';

    $component = BladeComponent::classBased(
        name: 'alert',
        classPath: $classPath,
        className: 'App\\View\\Components\\Alert',
        basePath: $this->tempDir,
        viewPath: $viewPath,
    );

    expect($component->name)->toBe('alert');
    expect($component->isClassBased)->toBeTrue();
    expect($component->viewPath)->toBe($viewPath);
    expect($component->classPath)->toBe($classPath);
    expect($component->className)->toBe('App\\View\\Components\\Alert');
    expect($component->totalSize)->toBeGreaterThan(0);
});

test('it creates class-based component without view (inline)', function () {
    $classPath = $this->tempDir.'/app/View/Components/Alert.php';

    $component = BladeComponent::classBased(
        name: 'alert',
        classPath: $classPath,
        className: 'App\\View\\Components\\Alert',
        basePath: $this->tempDir,
        viewPath: null,
    );

    expect($component->name)->toBe('alert');
    expect($component->isClassBased)->toBeTrue();
    expect($component->viewPath)->toBeNull();
    expect($component->isInline())->toBeTrue();
});

test('it generates correct tag name', function () {
    $component = BladeComponent::anonymous(
        name: 'forms.input',
        viewPath: $this->tempDir.'/resources/views/components/button.blade.php',
        basePath: $this->tempDir,
    );

    expect($component->getTagName())->toBe('x-forms.input');
});

test('it generates correct view name', function () {
    $component = BladeComponent::anonymous(
        name: 'forms.input',
        viewPath: $this->tempDir.'/resources/views/components/button.blade.php',
        basePath: $this->tempDir,
    );

    expect($component->getViewName())->toBe('components.forms.input');
});

test('it serializes to JSON correctly', function () {
    $component = BladeComponent::anonymous(
        name: 'button',
        viewPath: $this->tempDir.'/resources/views/components/button.blade.php',
        basePath: $this->tempDir,
    );

    $json = $component->jsonSerialize();

    expect($json)->toHaveKey('name', 'button');
    expect($json)->toHaveKey('view_path', 'resources/views/components/button.blade.php');
    expect($json)->toHaveKey('is_class_based', false);
    expect($json)->toHaveKey('size');
    expect($json)->toHaveKey('size_human');
});

test('it creates component from manifest data', function () {
    $data = [
        'name' => 'button',
        'view_path' => 'resources/views/components/button.blade.php',
        'is_class_based' => false,
        'class_path' => null,
        'class_name' => null,
        'size' => 100,
        'modified_at' => '2024-01-15 10:30:00',
    ];

    $component = BladeComponent::fromManifest($data, $this->tempDir);

    expect($component->name)->toBe('button');
    expect($component->viewRelativePath)->toBe('resources/views/components/button.blade.php');
    expect($component->isClassBased)->toBeFalse();
    expect($component->totalSize)->toBe(100);
});

test('it formats file size for humans', function () {
    $component = BladeComponent::anonymous(
        name: 'button',
        viewPath: $this->tempDir.'/resources/views/components/button.blade.php',
        basePath: $this->tempDir,
    );

    expect($component->humanFileSize())->toMatch('/^\d+(\.\d+)?\s(B|KB|MB|GB)$/');
});
