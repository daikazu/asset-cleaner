<?php

declare(strict_types=1);

namespace Daikazu\AssetCleaner\DTOs;

use JsonSerializable;

final readonly class BladeComponent implements JsonSerializable
{
    public function __construct(
        public string $name,
        public ?string $viewPath,
        public ?string $viewRelativePath,
        public bool $isClassBased,
        public ?string $classPath,
        public ?string $classRelativePath,
        public ?string $className,
        public int $totalSize,
        public ?int $modifiedAt = null,
    ) {}

    /**
     * Create an anonymous (file-based) component.
     */
    public static function anonymous(
        string $name,
        string $viewPath,
        string $basePath,
    ): self {
        $viewRelativePath = str_replace($basePath.DIRECTORY_SEPARATOR, '', $viewPath);

        return new self(
            name: $name,
            viewPath: $viewPath,
            viewRelativePath: $viewRelativePath,
            isClassBased: false,
            classPath: null,
            classRelativePath: null,
            className: null,
            totalSize: file_exists($viewPath) ? (filesize($viewPath) ?: 0) : 0,
            modifiedAt: file_exists($viewPath) ? (filemtime($viewPath) ?: null) : null,
        );
    }

    /**
     * Create a class-based component with an associated view.
     */
    public static function classBased(
        string $name,
        string $classPath,
        string $className,
        string $basePath,
        ?string $viewPath = null,
    ): self {
        $classRelativePath = str_replace($basePath.DIRECTORY_SEPARATOR, '', $classPath);
        $viewRelativePath = $viewPath ? str_replace($basePath.DIRECTORY_SEPARATOR, '', $viewPath) : null;

        $classSize = file_exists($classPath) ? (filesize($classPath) ?: 0) : 0;
        $viewSize = $viewPath && file_exists($viewPath) ? (filesize($viewPath) ?: 0) : 0;

        $classModified = file_exists($classPath) ? filemtime($classPath) : null;
        $viewModified = $viewPath && file_exists($viewPath) ? filemtime($viewPath) : null;

        // Use the most recent modification time
        $modifiedAt = max($classModified ?: 0, $viewModified ?: 0) ?: null;

        return new self(
            name: $name,
            viewPath: $viewPath,
            viewRelativePath: $viewRelativePath,
            isClassBased: true,
            classPath: $classPath,
            classRelativePath: $classRelativePath,
            className: $className,
            totalSize: $classSize + $viewSize,
            modifiedAt: $modifiedAt,
        );
    }

    /**
     * Create from manifest data.
     */
    public static function fromManifest(array $data, string $basePath): self
    {
        $viewPath = isset($data['view_path']) ? $basePath.DIRECTORY_SEPARATOR.$data['view_path'] : null;
        $classPath = isset($data['class_path']) ? $basePath.DIRECTORY_SEPARATOR.$data['class_path'] : null;

        return new self(
            name: $data['name'],
            viewPath: $viewPath,
            viewRelativePath: $data['view_path'] ?? null,
            isClassBased: $data['is_class_based'] ?? false,
            classPath: $classPath,
            classRelativePath: $data['class_path'] ?? null,
            className: $data['class_name'] ?? null,
            totalSize: $data['size'] ?? 0,
            modifiedAt: isset($data['modified_at']) ? strtotime($data['modified_at']) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'view_path' => $this->viewRelativePath,
            'is_class_based' => $this->isClassBased,
            'class_path' => $this->classRelativePath,
            'class_name' => $this->className,
            'size' => $this->totalSize,
            'size_human' => $this->humanFileSize(),
            'modified_at' => $this->modifiedAt ? date('Y-m-d H:i:s', $this->modifiedAt) : null,
        ];
    }

    public function humanFileSize(): string
    {
        $bytes = $this->totalSize;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    /**
     * Get the component tag name (e.g., "x-forms.input").
     */
    public function getTagName(): string
    {
        return 'x-'.$this->name;
    }

    /**
     * Get the view name for @component directive (e.g., "components.forms.input").
     */
    public function getViewName(): string
    {
        return 'components.'.$this->name;
    }

    /**
     * Check if this is an inline-rendered component (class-based with no view file).
     */
    public function isInline(): bool
    {
        return $this->isClassBased && $this->viewPath === null;
    }
}
