<?php

declare(strict_types=1);

namespace Daikazu\AssetCleaner\DTOs;

use JsonSerializable;

final readonly class ImageAsset implements JsonSerializable
{
    public function __construct(
        public string $path,
        public string $relativePath,
        public string $filename,
        public string $extension,
        public int $size,
        public ?int $modifiedAt = null,
    ) {}

    public static function fromPath(string $absolutePath, string $basePath): self
    {
        $relativePath = str_replace($basePath.DIRECTORY_SEPARATOR, '', $absolutePath);

        return new self(
            path: $absolutePath,
            relativePath: $relativePath,
            filename: basename($absolutePath),
            extension: strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION)),
            size: filesize($absolutePath) ?: 0,
            modifiedAt: filemtime($absolutePath) ?: null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'path' => $this->relativePath,
            'filename' => $this->filename,
            'extension' => $this->extension,
            'size' => $this->size,
            'size_human' => $this->humanFileSize(),
            'modified_at' => $this->modifiedAt ? date('Y-m-d H:i:s', $this->modifiedAt) : null,
        ];
    }

    public function humanFileSize(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
