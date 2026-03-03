<?php

declare(strict_types=1);

/**
 * Flysystem.php
 *
 * PHP Version 8.3+
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */

namespace Blackcube\FileProvider;

use League\Flysystem\DirectoryListing;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathNormalizer;

/**
 * Abstract base class wrapping League\Flysystem.
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */
abstract class Flysystem implements FlysystemProviderInterface
{
    protected Filesystem $flysystem;

    public function __construct(
        protected array $config = [],
        protected ?PathNormalizer $pathNormalizer = null,
    ) {
        $adapter = $this->prepareAdapter();
        $this->flysystem = new Filesystem($adapter, $this->config, $this->pathNormalizer);
    }

    abstract protected function prepareAdapter(): FilesystemAdapter;

    public function fileExists(string $path): bool
    {
        return $this->flysystem->fileExists($path);
    }

    public function directoryExists(string $path): bool
    {
        return $this->flysystem->directoryExists($path);
    }

    public function has(string $path): bool
    {
        return $this->flysystem->has($path);
    }

    public function read(string $path): string
    {
        return $this->flysystem->read($path);
    }

    public function readStream(string $path): mixed
    {
        return $this->flysystem->readStream($path);
    }

    public function write(string $path, string $contents, array $config = []): void
    {
        $this->flysystem->write($path, $contents, $config);
    }

    public function writeStream(string $path, mixed $stream, array $config = []): void
    {
        $this->flysystem->writeStream($path, $stream, $config);
    }

    public function delete(string $path): void
    {
        $this->flysystem->delete($path);
    }

    public function deleteDirectory(string $path): void
    {
        $this->flysystem->deleteDirectory($path);
    }

    public function createDirectory(string $path, array $config = []): void
    {
        $this->flysystem->createDirectory($path, $config);
    }

    public function move(string $source, string $destination, array $config = []): void
    {
        $this->flysystem->move($source, $destination, $config);
    }

    public function copy(string $source, string $destination, array $config = []): void
    {
        $this->flysystem->copy($source, $destination, $config);
    }

    public function mimeType(string $path): string
    {
        return $this->flysystem->mimeType($path);
    }

    public function fileSize(string $path): int
    {
        return $this->flysystem->fileSize($path);
    }

    public function lastModified(string $path): int
    {
        return $this->flysystem->lastModified($path);
    }

    public function visibility(string $path): string
    {
        return $this->flysystem->visibility($path);
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $this->flysystem->setVisibility($path, $visibility);
    }

    public function listContents(string $path, bool $recursive = false): DirectoryListing
    {
        return $this->flysystem->listContents($path, $recursive);
    }
}
