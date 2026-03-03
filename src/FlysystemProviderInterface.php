<?php

declare(strict_types=1);

/**
 * FlysystemProviderInterface.php
 *
 * PHP Version 8.3+
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */

namespace Blackcube\FileProvider;

use League\Flysystem\DirectoryListing;

/**
 * Full interface for filesystem adapters.
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */
interface FlysystemProviderInterface
{
    public function fileExists(string $path): bool;

    public function directoryExists(string $path): bool;

    public function has(string $path): bool;

    public function read(string $path): string;

    /**
     * @return resource
     */
    public function readStream(string $path): mixed;

    public function write(string $path, string $contents, array $config = []): void;

    /**
     * @param resource $stream
     */
    public function writeStream(string $path, mixed $stream, array $config = []): void;

    public function delete(string $path): void;

    public function deleteDirectory(string $path): void;

    public function createDirectory(string $path, array $config = []): void;

    public function move(string $source, string $destination, array $config = []): void;

    public function copy(string $source, string $destination, array $config = []): void;

    public function mimeType(string $path): string;

    public function fileSize(string $path): int;

    public function lastModified(string $path): int;

    public function visibility(string $path): string;

    public function setVisibility(string $path, string $visibility): void;

    public function listContents(string $path, bool $recursive = false): DirectoryListing;
}
