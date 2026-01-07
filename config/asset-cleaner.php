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
        '**/public/build/**',
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
        '**/apple-touch-icon*.png',
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

    /*
    |--------------------------------------------------------------------------
    | Pattern Generators
    |--------------------------------------------------------------------------
    |
    | Enable or disable pattern generators for third-party package support.
    | Each generator adds additional search patterns for specific asset types.
    |
    | Options: true, false, or 'auto' (auto-detect if package is installed)
    |
    */
    'pattern_generators' => [
        'blade_icons' => 'auto', // Blade UI Kit Icons (SVG component support)
    ],

    /*
    |--------------------------------------------------------------------------
    | Blade Component Cleaner Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for scanning and cleaning unused Blade components.
    |
    */
    'blade_cleaner' => [
        /*
        |--------------------------------------------------------------------------
        | Anonymous Component Paths
        |--------------------------------------------------------------------------
        |
        | Directories to scan for anonymous (file-based) Blade components.
        | Paths are relative to the application base path.
        |
        */
        'anonymous_paths' => [
            'resources/views/components',
        ],

        /*
        |--------------------------------------------------------------------------
        | Class-Based Component Paths
        |--------------------------------------------------------------------------
        |
        | Directories to scan for class-based Blade components.
        | Paths are relative to the application base path.
        |
        */
        'class_paths' => [
            'app/View/Components',
        ],

        /*
        |--------------------------------------------------------------------------
        | Directories to Search for References
        |--------------------------------------------------------------------------
        |
        | These directories will be searched for references to Blade components.
        | Paths are relative to the application base path.
        |
        */
        'search_paths' => [
            'resources/views',
            'app',
            'routes',
            'config',
        ],

        /*
        |--------------------------------------------------------------------------
        | File Extensions to Search
        |--------------------------------------------------------------------------
        |
        | Only files with these extensions will be searched for component references.
        |
        */
        'search_extensions' => [
            'blade.php',
            'php',
        ],

        /*
        |--------------------------------------------------------------------------
        | Exclude Patterns
        |--------------------------------------------------------------------------
        |
        | Patterns to exclude from scanning. Supports glob patterns.
        | Components matching these patterns will be ignored.
        |
        */
        'exclude_patterns' => [
            '**/vendor/**',
            '**/node_modules/**',
        ],

        /*
        |--------------------------------------------------------------------------
        | Protected Patterns
        |--------------------------------------------------------------------------
        |
        | These component names or patterns will never be marked as unused.
        | Useful for layout components or other critical components.
        |
        */
        'protected_patterns' => [
            'layout',
            'layouts.*',
            'app-layout',
        ],

        /*
        |--------------------------------------------------------------------------
        | Manifest File Path
        |--------------------------------------------------------------------------
        |
        | Where to store the manifest of unused components.
        | Path is relative to the application base path.
        |
        */
        'manifest_path' => 'unused-components.json',

        /*
        |--------------------------------------------------------------------------
        | Backup Path
        |--------------------------------------------------------------------------
        |
        | Directory where component backups will be stored (relative to project root).
        | Separate from image asset backups for easier organization.
        |
        */
        'backup_path' => '.blade-cleaner-backup',
    ],
];
