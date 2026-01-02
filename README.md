<picture>
   <source media="(prefers-color-scheme: dark)" srcset="art/header-dark.png">
   <img alt="Logo for Laravel Asset Cleaner" src="art/header-light.png">
</picture>

[![Latest Version on Packagist](https://img.shields.io/packagist/v/daikazu/asset-cleaner.svg?style=flat-square)](https://packagist.org/packages/daikazu/asset-cleaner)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/daikazu/asset-cleaner/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/daikazu/asset-cleaner/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/daikazu/asset-cleaner/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/daikazu/asset-cleaner/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/daikazu/asset-cleaner.svg?style=flat-square)](https://packagist.org/packages/daikazu/asset-cleaner)

# Laravel Asset Cleaner
This package helps you clean up your Laravel app in development by tracking down image files that aren’t being used anymore. It scans through your codebase, finds where images are referenced, and flags any leftover files that have no connection to the app so you can safely remove the clutter and keep things tidy.

## Features

- Scans configurable directories for image assets
- Searches Blade, PHP, JavaScript, Vue, CSS, and more for references
- Generates a reviewable manifest before deletion
- Supports dry-run mode to preview changes
- Automatic backup before deletion
- Protected patterns for critical assets (favicons, logos)
- One-shot "trust mode" for quick cleanup

## Installation

Install the package via Composer:

```bash
composer require daikazu/asset-cleaner --dev
```

Publish the config file:

```bash
php artisan vendor:publish --tag="asset-cleaner-config"
```

## Quick Start

### Step 1: Scan for unused assets

```bash
php artisan asset-cleaner:scan
```

This scans your project and generates an `unused-assets.json` manifest in your project root.

### Step 2: Review the manifest

Open `unused-assets.json` and review the list of unused assets. Remove any entries for files you want to keep.

```json
{
    "generated_at": "2024-01-15T10:30:00+00:00",
    "total_scanned": 150,
    "total_unused": 12,
    "total_size_human": "2.5 MB",
    "assets": [
        {
            "path": "public/images/old-banner.jpg",
            "filename": "old-banner.jpg",
            "size_human": "450 KB"
        }
    ]
}
```

### Step 3: Clean up

```bash
php artisan asset-cleaner:clean
```

This deletes the files listed in the manifest (with backup by default).

## Commands

### `asset-cleaner:scan`

Scan for unused image assets and generate a manifest.

```bash
# Generate manifest
php artisan asset-cleaner:scan

# Show statistics only (no manifest)
php artisan asset-cleaner:scan --stats
```

### `asset-cleaner:clean`

Delete unused assets based on the manifest or perform a one-shot cleanup.

```bash
# Delete from manifest (with confirmation)
php artisan asset-cleaner:clean

# Preview what would be deleted
php artisan asset-cleaner:clean --dry-run

# Skip confirmation prompt
php artisan asset-cleaner:clean --force

# One-shot mode: scan and delete without manifest
php artisan asset-cleaner:clean --trust

# Combine flags for CI/CD
php artisan asset-cleaner:clean --trust --force --dry-run

# Skip backup
php artisan asset-cleaner:clean --no-backup
```

## Configuration

```php
// config/asset-cleaner.php

return [
    // Directories to scan for image assets
    'scan_paths' => [
        'public',
        'resources',
    ],

    // File extensions considered as images
    'image_extensions' => [
        'jpg', 'jpeg', 'png', 'gif', 'svg',
        'webp', 'ico', 'bmp', 'tiff', 'avif',
    ],

    // Directories to search for references
    'search_paths' => [
        'app',
        'resources',
        'routes',
        'config',
        'database',
    ],

    // File types to search for image references
    'search_extensions' => [
        'php', 'blade.php',
        'js', 'jsx', 'ts', 'tsx', 'vue', 'svelte',
        'css', 'scss', 'sass', 'less',
        'json', 'yaml', 'yml',
        'md', 'mdx',
    ],

    // Patterns to exclude from scanning
    'exclude_patterns' => [
        '**/node_modules/**',
        '**/vendor/**',
        '**/.git/**',
    ],

    // Protected files (never marked as unused)
    'protected_patterns' => [
        '**/favicon.ico',
        '**/favicon.png',
        '**/apple-touch-icon.png',
        '**/logo.*',
    ],

    // Manifest file location
    'manifest_path' => 'unused-assets.json',

    // Backup files before deletion (to .asset-cleaner-backup/)
    'backup_before_delete' => true,
    'backup_path' => '.asset-cleaner-backup',
];
```

## Programmatic Usage

You can also use the package programmatically via the facade:

```php
use Daikazu\AssetCleaner\Facades\AssetCleaner;

// Get all image assets
$assets = AssetCleaner::scan();

// Find unused assets
$unused = AssetCleaner::findUnused();

// Get statistics
$stats = AssetCleaner::getStatistics();
// Returns: ['total' => 150, 'unused' => 12, 'used' => 138, ...]

// Generate manifest
AssetCleaner::generateManifest();

// Clean from manifest
$result = AssetCleaner::cleanFromManifest();

// One-shot cleanup
$result = AssetCleaner::cleanAll();

// Dry run
$result = AssetCleaner::cleanAll(dryRun: true);
```

## How It Works

1. **Scanning**: The package recursively scans configured directories for files matching image extensions.

2. **Reference Detection**: For each image found, the package searches your codebase for references using multiple strategies:
   - Full relative path (`public/images/hero.jpg`)
   - Path without `public/` prefix (`images/hero.jpg`)
   - Filename only (`hero.jpg`)
   - Filename without extension (`hero`)

3. **Manifest Generation**: Unused assets are written to a JSON manifest that you can review and edit.

4. **Deletion**: Files are deleted based on the manifest, with optional backup to `.asset-cleaner-backup/`.

## Best Practices

1. **Always review the manifest** before running clean, especially the first time.

2. **Use `--dry-run`** to preview changes before committing to deletion.

3. **Keep backups enabled** until you're confident in the results.

4. **Add to `.gitignore`**:
   ```
   unused-assets.json
   .asset-cleaner-backup/
   ```

5. **Protect critical assets** by adding patterns to `protected_patterns` in the config.

6. **Run after major refactors** when you've removed features or redesigned pages.

## Limitations

- Dynamic image references (e.g., `asset($variable)`) may not be detected
- Images referenced only in the database won't be detected
- External CDN references are not tracked

For dynamic references, add the pattern to `protected_patterns` or remove entries from the manifest before cleaning.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Mike Wall](https://github.com/daikazu)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
