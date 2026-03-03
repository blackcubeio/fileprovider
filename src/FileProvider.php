<?php

declare(strict_types=1);

/**
 * FileProvider.php
 *
 * PHP Version 8.3+
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */

namespace Blackcube\FileProvider;

use Blackcube\FileProvider\Contracts\FileProviderInterface;
use Blackcube\FileProvider\Exception\UnknownFilesystemException;
use Blackcube\FileProvider\Image\ImageManagerFactory;
use Intervention\Image\ImageManager;
use League\Flysystem\DirectoryListing;

/**
 * Multi-filesystem manager with alias support and image processing.
 *
 * Manages multiple filesystems with alias prefixes (@bltmp, @blfs, etc.)
 * Routes operations to the appropriate filesystem based on path prefix.
 * Supports fluent image processing via Intervention Image.
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */
class FileProvider implements FileProviderInterface, FlysystemProviderInterface
{
    public const ALIAS_TMP = '@bltmp';
    public const ALIAS_FS = '@blfs';

    /** @var array<int, array{0: string, 1: array<string, mixed>}> */
    private array $processors = [];
    private ?ImageManager $imageManager = null;

    /**
     * @param array<string, FlysystemProviderInterface> $filesystems Map of alias => filesystem
     * @param string $defaultAlias Default alias when path has no prefix
     * @param string|null $imageDriver Force image driver (gd, imagick, vips) or null for auto-detection
     */
    public function __construct(
        private array $filesystems = [],
        private readonly string $defaultAlias = self::ALIAS_FS,
        ?string $imageDriver = null,
    ) {
        $this->imageManager = ImageManagerFactory::create($imageDriver);
    }

    /**
     * Register a filesystem with an alias
     */
    public function addFilesystem(string $alias, FlysystemProviderInterface $filesystem): self
    {
        $this->filesystems[$alias] = $filesystem;
        return $this;
    }

    /**
     * Get a filesystem by its alias
     */
    public function getFilesystem(string $alias): FlysystemProviderInterface
    {
        if (!isset($this->filesystems[$alias])) {
            throw new UnknownFilesystemException("Unknown filesystem alias: {$alias}");
        }
        return $this->filesystems[$alias];
    }

    /**
     * Check if a filesystem exists for the given alias
     */
    public function hasFilesystem(string $alias): bool
    {
        return isset($this->filesystems[$alias]);
    }

    /**
     * Get all registered filesystem aliases
     *
     * @return string[]
     */
    public function getAliases(): array
    {
        return array_keys($this->filesystems);
    }

    /**
     * Resolve a path to [alias, relativePath]
     *
     * @return array{0: string, 1: string} [alias, relativePath]
     */
    public function resolvePath(string $path): array
    {
        foreach (array_keys($this->filesystems) as $alias) {
            if (str_starts_with($path, $alias . '/')) {
                return [$alias, substr($path, strlen($alias) + 1)];
            }
            if ($path === $alias) {
                return [$alias, ''];
            }
        }

        return [$this->defaultAlias, $path];
    }

    /**
     * Check if the path prefix is handled by this provider
     */
    public function canHandle(string $path): bool
    {
        foreach (array_keys($this->filesystems) as $alias) {
            if (str_starts_with($path, $alias . '/') || $path === $alias) {
                return true;
            }
        }
        return false;
    }

    // ========== FLUENT PROCESSORS ==========

    /**
     * @param array<string, mixed> $options
     */
    public function resize(?int $width, ?int $height = null, array $options = []): static
    {
        $clone = clone $this;
        $clone->processors[] = ['resize', compact('width', 'height') + $options];
        return $clone;
    }

    public function watermark(string $image, string $position = 'bottom-right', int $padding = 10): static
    {
        $clone = clone $this;
        $clone->processors[] = ['watermark', compact('image', 'position', 'padding')];
        return $clone;
    }

    public function crop(int $width, int $height, ?int $x = null, ?int $y = null): static
    {
        $clone = clone $this;
        $clone->processors[] = ['crop', compact('width', 'height', 'x', 'y')];
        return $clone;
    }

    public function rotate(float $angle): static
    {
        $clone = clone $this;
        $clone->processors[] = ['rotate', compact('angle')];
        return $clone;
    }

    public function flip(string $direction = 'horizontal'): static
    {
        $clone = clone $this;
        $clone->processors[] = ['flip', compact('direction')];
        return $clone;
    }

    public function quality(int $quality): static
    {
        $clone = clone $this;
        $clone->processors[] = ['quality', compact('quality')];
        return $clone;
    }

    public function format(string $format): static
    {
        $clone = clone $this;
        $clone->processors[] = ['format', compact('format')];
        return $clone;
    }

    public function greyscale(): static
    {
        $clone = clone $this;
        $clone->processors[] = ['greyscale', []];
        return $clone;
    }

    public function blur(int $amount = 5): static
    {
        $clone = clone $this;
        $clone->processors[] = ['blur', compact('amount')];
        return $clone;
    }

    /**
     * Get the filesystem and relative path for a given path
     *
     * @return array{0: FlysystemProviderInterface, 1: string} [filesystem, relativePath]
     */
    private function resolve(string $path): array
    {
        [$alias, $relativePath] = $this->resolvePath($path);

        if (!isset($this->filesystems[$alias])) {
            throw new UnknownFilesystemException("Unknown filesystem alias: {$alias}");
        }

        return [$this->filesystems[$alias], $relativePath];
    }

    public function fileExists(string $path): bool
    {
        [$fs, $relativePath] = $this->resolve($path);
        return $fs->fileExists($relativePath);
    }

    public function directoryExists(string $path): bool
    {
        [$fs, $relativePath] = $this->resolve($path);
        return $fs->directoryExists($relativePath);
    }

    public function has(string $path): bool
    {
        [$fs, $relativePath] = $this->resolve($path);
        return $fs->has($relativePath);
    }

    public function read(string $path): string
    {
        [$fs, $relativePath] = $this->resolve($path);
        $contents = $fs->read($relativePath);

        if (!empty($this->processors)) {
            $mimeType = $fs->mimeType($relativePath);
            $contents = $this->applyProcessors($contents, $mimeType);
        }

        return $contents;
    }

    public function readStream(string $path): mixed
    {
        [$fs, $relativePath] = $this->resolve($path);
        $stream = $fs->readStream($relativePath);

        if (!empty($this->processors)) {
            $mimeType = $fs->mimeType($relativePath);
            $stream = $this->applyProcessorsToStream($stream, $mimeType);
        }

        return $stream;
    }

    public function write(string $path, string $contents, array $config = []): void
    {
        if (!empty($this->processors)) {
            $mimeType = $this->detectMimeType($path, $contents);
            $contents = $this->applyProcessors($contents, $mimeType);
        }

        [$fs, $relativePath] = $this->resolve($path);
        $fs->write($relativePath, $contents, $config);
    }

    public function writeStream(string $path, mixed $stream, array $config = []): void
    {
        if (!empty($this->processors)) {
            $mimeType = $this->detectMimeTypeFromPath($path);
            $stream = $this->applyProcessorsToStream($stream, $mimeType);
        }

        [$fs, $relativePath] = $this->resolve($path);
        $fs->writeStream($relativePath, $stream, $config);
    }

    public function delete(string $path): void
    {
        [$fs, $relativePath] = $this->resolve($path);
        $fs->delete($relativePath);
    }

    public function deleteDirectory(string $path): void
    {
        [$fs, $relativePath] = $this->resolve($path);
        $fs->deleteDirectory($relativePath);
    }

    public function createDirectory(string $path, array $config = []): void
    {
        [$fs, $relativePath] = $this->resolve($path);
        $fs->createDirectory($relativePath, $config);
    }

    /**
     * Move a file, potentially across filesystems
     */
    public function move(string $source, string $destination, array $config = []): void
    {
        [$srcFs, $srcPath] = $this->resolve($source);
        [$dstFs, $dstPath] = $this->resolve($destination);

        if ($srcFs === $dstFs) {
            $srcFs->move($srcPath, $dstPath, $config);
        } else {
            // Cross-filesystem move: copy then delete
            $stream = $srcFs->readStream($srcPath);
            $dstFs->writeStream($dstPath, $stream, $config);
            if (is_resource($stream)) {
                fclose($stream);
            }
            $srcFs->delete($srcPath);
        }
    }

    /**
     * Copy a file, potentially across filesystems
     */
    public function copy(string $source, string $destination, array $config = []): void
    {
        [$srcFs, $srcPath] = $this->resolve($source);
        [$dstFs, $dstPath] = $this->resolve($destination);

        if ($srcFs === $dstFs) {
            $srcFs->copy($srcPath, $dstPath, $config);
        } else {
            // Cross-filesystem copy
            $stream = $srcFs->readStream($srcPath);
            $dstFs->writeStream($dstPath, $stream, $config);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function mimeType(string $path): string
    {
        [$fs, $relativePath] = $this->resolve($path);
        return $fs->mimeType($relativePath);
    }

    public function fileSize(string $path): int
    {
        [$fs, $relativePath] = $this->resolve($path);
        return $fs->fileSize($relativePath);
    }

    public function lastModified(string $path): int
    {
        [$fs, $relativePath] = $this->resolve($path);
        return $fs->lastModified($relativePath);
    }

    public function visibility(string $path): string
    {
        [$fs, $relativePath] = $this->resolve($path);
        return $fs->visibility($relativePath);
    }

    public function setVisibility(string $path, string $visibility): void
    {
        [$fs, $relativePath] = $this->resolve($path);
        $fs->setVisibility($relativePath, $visibility);
    }

    public function listContents(string $path, bool $recursive = false): DirectoryListing
    {
        [$fs, $relativePath] = $this->resolve($path);
        return $fs->listContents($relativePath, $recursive);
    }

    // ========== IMAGE PROCESSING ==========

    protected function applyProcessors(string $contents, string $mimeType): string
    {
        if (empty($this->processors)) {
            return $contents;
        }

        if ($this->imageManager === null || !str_starts_with($mimeType, 'image/')) {
            return $contents;
        }

        $image = $this->imageManager->read($contents);

        foreach ($this->processors as [$processor, $options]) {
            $image = match ($processor) {
                'resize' => $image->scale($options['width'], $options['height']),
                'watermark' => $image->place($options['image'], $options['position'], $options['padding'], $options['padding']),
                'crop' => $options['x'] !== null
                    ? $image->crop($options['width'], $options['height'], $options['x'], $options['y'])
                    : $image->cover($options['width'], $options['height']),
                'rotate' => $image->rotate($options['angle']),
                'flip' => $options['direction'] === 'horizontal' ? $image->flop() : $image->flip(),
                'greyscale' => $image->greyscale(),
                'blur' => $image->blur($options['amount']),
                'quality', 'format' => $image,
                default => $image,
            };
        }

        $quality = $this->getProcessorOption('quality', 'quality') ?? 90;
        $format = $this->getProcessorOption('format', 'format');

        // Encode based on format or original mime type
        if ($format !== null) {
            return (string) match ($format) {
                'jpg', 'jpeg' => $image->toJpeg($quality),
                'png' => $image->toPng(),
                'webp' => $image->toWebp($quality),
                'gif' => $image->toGif(),
                'avif' => $image->toAvif($quality),
                'bmp' => $image->toBmp(),
                'tiff', 'tif' => $image->toTiff($quality),
                default => $image->toJpeg($quality),
            };
        }

        // Encode based on original mime type
        return (string) match ($mimeType) {
            'image/jpeg' => $image->toJpeg($quality),
            'image/png' => $image->toPng(),
            'image/webp' => $image->toWebp($quality),
            'image/gif' => $image->toGif(),
            'image/avif' => $image->toAvif($quality),
            'image/bmp' => $image->toBmp(),
            'image/tiff' => $image->toTiff($quality),
            default => $image->toJpeg($quality),
        };
    }

    /**
     * @param resource $stream
     * @return resource
     */
    protected function applyProcessorsToStream(mixed $stream, string $mimeType): mixed
    {
        if (empty($this->processors) || $this->imageManager === null || !str_starts_with($mimeType, 'image/')) {
            return $stream;
        }

        $contents = stream_get_contents($stream);
        if ($contents === false) {
            return $stream;
        }

        $processed = $this->applyProcessors($contents, $mimeType);

        $newStream = fopen('php://temp', 'r+');
        fwrite($newStream, $processed);
        rewind($newStream);

        return $newStream;
    }

    private function getProcessorOption(string $processor, string $key): mixed
    {
        foreach ($this->processors as [$proc, $options]) {
            if ($proc === $processor && isset($options[$key])) {
                return $options[$key];
            }
        }
        return null;
    }

    private function detectMimeType(string $path, string $contents): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($contents);
        return $mimeType !== false ? $mimeType : $this->detectMimeTypeFromPath($path);
    }

    private function detectMimeTypeFromPath(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };
    }
}
