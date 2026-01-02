<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Directories to Scan for Image Assets
    |--------------------------------------------------------------------------
    |
    | These directories will be scanned recursively for image files.
    | Paths are relative to the application base path.
    |
    */
    'scan_paths' => [
        'public',
        'resources',
    ],

    /*
    |--------------------------------------------------------------------------
    | Image File Extensions
    |--------------------------------------------------------------------------
    |
    | File extensions that should be considered as image assets.
    |
    */
    'image_extensions' => [
        'jpg',
        'jpeg',
        'png',
        'gif',
        'svg',
        'webp',
        'ico',
        'bmp',
        'tiff',
        'tif',
        'avif',
    ],

    /*
    |--------------------------------------------------------------------------
    | Directories to Search for References
    |--------------------------------------------------------------------------
    |
    | These directories will be searched for references to image assets.
    | Paths are relative to the application base path.
    |
    */
    'search_paths' => [
        'app',
        'resources',
        'routes',
        'config',
        'database',
    ],

    /*
    |--------------------------------------------------------------------------
    | File Extensions to Search
    |--------------------------------------------------------------------------
    |
    | Only files with these extensions will be searched for image references.
    |
    */
    'search_extensions' => [
        // PHP & Blade
        'php',
        'blade.php',

        // JavaScript & TypeScript
        'js',
        'jsx',
        'ts',
        'tsx',
        'vue',
        'svelte',

        // Styles
        'css',
        'scss',
        'sass',
        'less',
        'styl',

        // Config & Data
        'json',
        'yaml',
        'yml',

        // Markdown & Docs
        'md',
        'mdx',
    ],

    /*
    |--------------------------------------------------------------------------
    | Exclude Patterns
    |--------------------------------------------------------------------------
    |
    | Patterns to exclude from scanning. Supports glob patterns.
    | Images matching these patterns will be ignored.
    |
    */
    'exclude_patterns' => [
        '**/node_modules/**',
        '**/vendor/**',
        '**/.git/**',
        '**/cache/**',
        '**/storage/framework/**',
    ],

    /*
    |--------------------------------------------------------------------------
    | Always Exclude (Never Delete)
    |--------------------------------------------------------------------------
    |
    | These specific files or patterns will never be marked as unused.
    | Useful for favicons, logos, or other critical assets.
    |
    */
    'protected_patterns' => [
        '**/favicon.ico',
        '**/favicon.png',
        '**/apple-touch-icon.png',
        '**/logo.*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Manifest File Path
    |--------------------------------------------------------------------------
    |
    | Where to store the manifest of unused assets.
    | Path is relative to the application base path.
    |
    */
    'manifest_path' => 'unused-assets.json',

    /*
    |--------------------------------------------------------------------------
    | Backup Before Delete
    |--------------------------------------------------------------------------
    |
    | When true, assets will be backed up before deletion.
    |
    */
    'backup_before_delete' => true,

    /*
    |--------------------------------------------------------------------------
    | Backup Path
    |--------------------------------------------------------------------------
    |
    | Directory where backups will be stored (relative to project root).
    | Uses a dotfile folder to keep it unobtrusive but visible.
    |
    */
    'backup_path' => '.asset-cleaner-backup',
];
