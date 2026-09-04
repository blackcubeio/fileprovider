<?php

declare(strict_types=1);

/**
 * FileProvider.php
 *
 * PHP Version 8.4
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 */

namespace Blackcube\FileProvider;

use Blackcube\FileProvider\Exception\UnknownFilesystemException;
use Blackcube\FileProvider\Flysystem\FlysystemAwsS3;
use Blackcube\FileProvider\Flysystem\FlysystemFtp;
use Blackcube\FileProvider\Flysystem\FlysystemLocal;
use Blackcube\FileProvider\Flysystem\FlysystemSftp;
use Blackcube\FileProvider\Image\ImageManagerFactory;
use Blackcube\FileProvider\Interfaces\FileProviderInterface;
use Blackcube\FileProvider\Interfaces\FlysystemProviderInterface;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use Intervention\Image\Direction;
use League\Flysystem\DirectoryListing;
use Yiisoft\Aliases\Aliases;

/**
 * Multi-filesystem manager with alias support and image processing.
 *
 * Manages multiple filesystems with alias prefixes (@bltmp, @blfs, etc.)
 * Routes operations to the appropriate filesystem based on path prefix.
 * Supports fluent image processing via Intervention Image.
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 */
class FileProvider implements FileProviderInterface, FlysystemProviderInterface
{
    public const ALIAS_TMP = '@bltmp';
    public const ALIAS_FS = '@blfs';

    /** @var array<int, array{0: string, 1: array<string, mixed>}> */
    private array $processors = [];
    private ?int $quality = null;
    private ?string $format = null;
    private readonly ?ImageManager $imageManager;
    /** @var array<string, FlysystemProviderInterface> */
    private array $filesystems = [];

    public function __construct(
        private readonly Aliases $aliases,
        array $filesystems = [],
        private readonly string $defaultAlias = self::ALIAS_FS,
        private readonly string $tempAlias = self::ALIAS_TMP,
        ?string $imageDriver = null,
    ) {
        $this->imageManager = ImageManagerFactory::create($imageDriver);

        foreach ($filesystems as $alias => $fsConfig) {
            $this->addFilesystem($alias, match ($fsConfig['type']) {
                'local' => new FlysystemLocal(
                    path: $this->aliases->get($fsConfig['path']),
                ),
                's3' => new FlysystemAwsS3(
                    bucket: $fsConfig['bucket'],
                    key: $fsConfig['key'] ?? null,
                    secret: $fsConfig['secret'] ?? null,
                    region: $fsConfig['region'] ?? null,
                    endpoint: $fsConfig['endpoint'] ?? null,
                    prefix: $fsConfig['prefix'] ?? '',
                    pathStyleEndpoint: $fsConfig['pathStyleEndpoint'] ?? false,
                    version: $fsConfig['version'] ?? 'latest',
                ),
                'ftp' => new FlysystemFtp(
                    host: $fsConfig['host'],
                    username: $fsConfig['username'],
                    password: $fsConfig['password'],
                    root: $fsConfig['root'] ?? '/',
                    port: $fsConfig['port'] ?? 21,
                    ssl: $fsConfig['ssl'] ?? false,
                ),
                'sftp' => new FlysystemSftp(
                    host: $fsConfig['host'],
                    username: $fsConfig['username'],
                    password: $fsConfig['password'] ?? null,
                    privateKey: $fsConfig['privateKey'] ?? null,
                    root: $fsConfig['root'] ?? '/',
                    port: $fsConfig['port'] ?? 22,
                ),
                default => throw new \InvalidArgumentException(
                    'Unknown filesystem type: '.$fsConfig['type']
                ),
            });
        }
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
        if (isset($this->filesystems[$alias]) === false) {
            throw new UnknownFilesystemException('Unknown filesystem alias: '.$alias);
        }
        return $this->filesystems[$alias];
    }

    /**
     * Check if a filesystem exists for the given alias
     */
    public function hasFilesystem(string $alias): bool
    {
        return isset($this->filesystems[$alias]) === true;
    }

    /**
     * Default alias used to store a file when none is specified.
     */
    public function getDefaultAlias(): string
    {
        return $this->defaultAlias;
    }

    /**
     * Alias of the temporary filesystem (resumable uploads land there).
     */
    public function getTempAlias(): string
    {
        return $this->tempAlias;
    }

    /**
     * Get the registered filesystem aliases.
     *
     * @param bool $withTmp include the temporary alias (default: true)
     * @return string[]
     */
    public function getAliases(bool $withTmp = true): array
    {
        $aliases = array_keys($this->filesystems);
        if ($withTmp === false) {
            $filtered = [];
            foreach ($aliases as $alias) {
                if ($alias !== $this->tempAlias) {
                    $filtered[] = $alias;
                }
            }
            $aliases = $filtered;
        }
        return $aliases;
    }

    /**
     * Resolve a path to [alias, relativePath]
     *
     * @return array{0: string, 1: string} [alias, relativePath]
     */
    public function resolvePath(string $path): array
    {
        foreach (array_keys($this->filesystems) as $alias) {
            if (str_starts_with($path, $alias.'/') === true) {
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
            if (str_starts_with($path, $alias.'/') === true || $path === $alias) {
                return true;
            }
        }
        return false;
    }

    public function isTempPath(string $path): bool
    {
        return str_starts_with($path, $this->tempAlias.'/') === true;
    }

    public function scale(?int $width, ?int $height = null): static
    {
        $clone = clone $this;
        $clone->processors[] = ['scale', compact('width', 'height')];
        return $clone;
    }

    public function cover(int $width, int $height): static
    {
        $clone = clone $this;
        $clone->processors[] = ['cover', compact('width', 'height')];
        return $clone;
    }

    public function pad(int $width, int $height, string $background = '#000000'): static
    {
        $clone = clone $this;
        $clone->processors[] = ['pad', compact('width', 'height', 'background')];
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
        $clone->quality ??= $quality;
        return $clone;
    }

    public function format(string $format): static
    {
        $clone = clone $this;
        $clone->format ??= $format;
        return $clone;
    }

    public function grayscale(): static
    {
        $clone = clone $this;
        $clone->processors[] = ['grayscale', []];
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
        if (empty($this->filesystems) === true) {
            throw new \RuntimeException(
                'No filesystems configured. Set filesystems in params.php under blackcube/fileprovider.'
            );
        }

        [$alias, $relativePath] = $this->resolvePath($path);

        if (isset($this->filesystems[$alias]) === false) {
            throw new UnknownFilesystemException('Unknown filesystem alias: '.$alias);
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

        if ($this->hasProcessing() === true) {
            $mimeType = $fs->mimeType($relativePath);
            $contents = $this->applyProcessors($contents, $mimeType);
        }

        return $contents;
    }

    public function readStream(string $path): mixed
    {
        [$fs, $relativePath] = $this->resolve($path);
        $stream = $fs->readStream($relativePath);

        if ($this->hasProcessing() === true) {
            $mimeType = $fs->mimeType($relativePath);
            $processed = $this->applyProcessorsToStream($stream, $mimeType);
            if ($processed !== $stream && is_resource($stream) === true) {
                fclose($stream);
            }
            $stream = $processed;
        }

        return $stream;
    }

    public function write(string $path, string $contents, array $config = []): void
    {
        if ($this->hasProcessing() === true) {
            $mimeType = $this->detectMimeType($path, $contents);
            $contents = $this->applyProcessors($contents, $mimeType);
        }

        [$fs, $relativePath] = $this->resolve($path);
        $fs->write($relativePath, $contents, $config);
    }

    public function writeStream(string $path, mixed $stream, array $config = []): void
    {
        if ($this->hasProcessing() === true) {
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
            $stream = $srcFs->readStream($srcPath);
            try {
                $dstFs->writeStream($dstPath, $stream, $config);
            } finally {
                if (is_resource($stream) === true) {
                    fclose($stream);
                }
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
            $stream = $srcFs->readStream($srcPath);
            try {
                $dstFs->writeStream($dstPath, $stream, $config);
            } finally {
                if (is_resource($stream) === true) {
                    fclose($stream);
                }
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

    /**
     * Whether a processor or an encoding option has been stacked on this instance.
     */
    private function hasProcessing(): bool
    {
        return $this->processors !== [] || $this->quality !== null || $this->format !== null;
    }

    protected function applyProcessors(string $contents, string $mimeType): string
    {
        if ($this->hasProcessing() === false) {
            return $contents;
        }

        if ($this->imageManager === null || str_starts_with($mimeType, 'image/') === false) {
            return $contents;
        }

        if ($this->imageManager->driver->supports($mimeType) === false) {
            return $contents;
        }

        $image = $this->imageManager->decode($contents);

        foreach ($this->processors as [$processor, $options]) {
            $image = match ($processor) {
                'scale' => $image->scale($options['width'], $options['height']),
                'cover' => $image->cover($options['width'], $options['height']),
                'pad' => $image->contain($options['width'], $options['height'], $options['background']),
                'watermark' => $image->place($options['image'], $options['position'], $options['padding'], $options['padding']),
                'crop' => $options['x'] !== null
                    ? $image->crop($options['width'], $options['height'], $options['x'], $options['y'])
                    : $image->cover($options['width'], $options['height']),
                'rotate' => $image->rotate($options['angle']),
                'flip' => $image->flip($options['direction'] === 'horizontal' ? Direction::HORIZONTAL : Direction::VERTICAL),
                'grayscale' => $image->grayscale(),
                'blur' => $image->blur($options['amount']),
                default => $image,
            };
        }

        $quality = $this->quality ?? 90;

        return (string) match ($this->format ?? $this->formatFromMimeType($mimeType)) {
            'png' => $image->encodeUsingFormat(Format::PNG),
            'gif' => $image->encodeUsingFormat(Format::GIF),
            'bmp' => $image->encodeUsingFormat(Format::BMP),
            'webp' => $image->encodeUsingFormat(Format::WEBP, quality: $quality),
            'avif' => $image->encodeUsingFormat(Format::AVIF, quality: $quality),
            'tiff', 'tif' => $image->encodeUsingFormat(Format::TIFF, quality: $quality),
            default => $image->encodeUsingFormat(Format::JPEG, quality: $quality),
        };
    }

    /**
     * Output format matching the source mime type, used when none is forced.
     */
    private function formatFromMimeType(string $mimeType): string
    {
        return match ($mimeType) {
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/bmp' => 'bmp',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            'image/tiff' => 'tiff',
            default => 'jpg',
        };
    }

    /**
     * @param resource $stream
     * @return resource
     */
    protected function applyProcessorsToStream(mixed $stream, string $mimeType): mixed
    {
        if ($this->hasProcessing() === false || $this->imageManager === null || str_starts_with($mimeType, 'image/') === false) {
            return $stream;
        }

        if ($this->imageManager->driver->supports($mimeType) === false) {
            return $stream;
        }

        $contents = stream_get_contents($stream);
        if ($contents === false) {
            return $stream;
        }

        $processed = $this->applyProcessors($contents, $mimeType);

        $newStream = fopen('php://temp', 'r+');
        if ($newStream === false) {
            return $stream;
        }

        fwrite($newStream, $processed);
        rewind($newStream);

        return $newStream;
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
