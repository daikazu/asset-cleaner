<?php

declare(strict_types=1);

use Daikazu\AssetCleaner\DTOs\BladeComponent;
use Daikazu\AssetCleaner\Services\ComponentDeleter;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/component-deleter-test-'.uniqid();
    mkdir($this->tempDir.'/resources/views/components', 0755, true);
    mkdir($this->tempDir.'/app/View/Components', 0755, true);
    mkdir($this->tempDir.'/.asset-cleaner-backup', 0755, true);

    // Create test files
    file_put_contents(
        $this->tempDir.'/resources/views/components/button.blade.php',
        '<button>{{ $slot }}</button>'
    );
    file_put_contents(
        $this->tempDir.'/app/View/Components/Alert.php',
        '<?php class Alert extends Component {}'
    );
    file_put_contents(
        $this->tempDir.'/resources/views/components/alert.blade.php',
        '<div class="alert">{{ $slot }}</div>'
    );
});

afterEach(function () {
    deleteDirectoryDeleter($this->tempDir);
});

function deleteDirectoryDeleter(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir.'/'.$file;
        is_dir($path) ? deleteDirectoryDeleter($path) : unlink($path);
    }
    rmdir($dir);
}

test('it deletes anonymous component view file', function () {
    $deleter = new ComponentDeleter(
        backupBeforeDelete: false,
        backupPath: '.asset-cleaner-backup',
        basePath: $this->tempDir,
    );

    $component = BladeComponent::anonymous(
        name: 'button',
        viewPath: $this->tempDir.'/resources/views/components/button.blade.php',
        basePath: $this->tempDir,
    );

    $result = $deleter->delete(collect([$component]));

    expect($result['deleted'])->toBe(1);
    expect(file_exists($this->tempDir.'/resources/views/components/button.blade.php'))->toBeFalse();
});

test('it deletes class-based component with both class and view files', function () {
    $deleter = new ComponentDeleter(
        backupBeforeDelete: false,
        backupPath: '.asset-cleaner-backup',
        basePath: $this->tempDir,
    );

    $component = BladeComponent::classBased(
        name: 'alert',
        classPath: $this->tempDir.'/app/View/Components/Alert.php',
        className: 'App\\View\\Components\\Alert',
        basePath: $this->tempDir,
        viewPath: $this->tempDir.'/resources/views/components/alert.blade.php',
    );

    $result = $deleter->delete(collect([$component]));

    expect($result['deleted'])->toBe(1);
    expect(file_exists($this->tempDir.'/app/View/Components/Alert.php'))->toBeFalse();
    expect(file_exists($this->tempDir.'/resources/views/components/alert.blade.php'))->toBeFalse();
});

test('it creates backups before deletion', function () {
    $deleter = new ComponentDeleter(
        backupBeforeDelete: true,
        backupPath: '.asset-cleaner-backup',
        basePath: $this->tempDir,
    );

    $component = BladeComponent::anonymous(
        name: 'button',
        viewPath: $this->tempDir.'/resources/views/components/button.blade.php',
        basePath: $this->tempDir,
    );

    $result = $deleter->delete(collect([$component]));

    expect($result['backed_up'])->toBe(1);
    expect($result['deleted'])->toBe(1);

    // Check backup exists
    $backupDir = $this->tempDir.'/.asset-cleaner-backup';
    $backupDirs = array_diff(scandir($backupDir), ['.', '..']);
    expect(count($backupDirs))->toBeGreaterThan(0);
});

test('it performs dry run without deleting files', function () {
    $deleter = new ComponentDeleter(
        backupBeforeDelete: true,
        backupPath: '.asset-cleaner-backup',
        basePath: $this->tempDir,
    );

    $component = BladeComponent::anonymous(
        name: 'button',
        viewPath: $this->tempDir.'/resources/views/components/button.blade.php',
        basePath: $this->tempDir,
    );

    $result = $deleter->delete(collect([$component]), dryRun: true);

    expect($result['deleted'])->toBe(1);
    expect($result['backed_up'])->toBe(0);
    expect(file_exists($this->tempDir.'/resources/views/components/button.blade.php'))->toBeTrue();
});

test('it reports failed deletions for missing files', function () {
    $deleter = new ComponentDeleter(
        backupBeforeDelete: false,
        backupPath: '.asset-cleaner-backup',
        basePath: $this->tempDir,
    );

    $component = BladeComponent::anonymous(
        name: 'nonexistent',
        viewPath: $this->tempDir.'/resources/views/components/nonexistent.blade.php',
        basePath: $this->tempDir,
    );

    $result = $deleter->delete(collect([$component]));

    expect($result['deleted'])->toBe(0);
    expect($result['failed'])->toHaveCount(1);
});

test('it cleans empty directories after deletion', function () {
    mkdir($this->tempDir.'/resources/views/components/nested', 0755, true);
    file_put_contents(
        $this->tempDir.'/resources/views/components/nested/deep.blade.php',
        '<div>Deep</div>'
    );

    $deleter = new ComponentDeleter(
        backupBeforeDelete: false,
        backupPath: '.asset-cleaner-backup',
        basePath: $this->tempDir,
    );

    $component = BladeComponent::anonymous(
        name: 'nested.deep',
        viewPath: $this->tempDir.'/resources/views/components/nested/deep.blade.php',
        basePath: $this->tempDir,
    );

    $deleter->delete(collect([$component]));

    expect(is_dir($this->tempDir.'/resources/views/components/nested'))->toBeFalse();
});

test('it returns total size of deleted components', function () {
    $deleter = new ComponentDeleter(
        backupBeforeDelete: false,
        backupPath: '.asset-cleaner-backup',
        basePath: $this->tempDir,
    );

    $component = BladeComponent::anonymous(
        name: 'button',
        viewPath: $this->tempDir.'/resources/views/components/button.blade.php',
        basePath: $this->tempDir,
    );

    $result = $deleter->delete(collect([$component]));

    expect($result['total_size'])->toBeGreaterThan(0);
});

test('it backs up multiple files for class-based components', function () {
    $deleter = new ComponentDeleter(
        backupBeforeDelete: true,
        backupPath: '.asset-cleaner-backup',
        basePath: $this->tempDir,
    );

    $component = BladeComponent::classBased(
        name: 'alert',
        classPath: $this->tempDir.'/app/View/Components/Alert.php',
        className: 'App\\View\\Components\\Alert',
        basePath: $this->tempDir,
        viewPath: $this->tempDir.'/resources/views/components/alert.blade.php',
    );

    $result = $deleter->delete(collect([$component]));

    expect($result['backed_up'])->toBe(2); // Both class and view backed up
    expect($result['deleted'])->toBe(1);   // One component deleted
});
