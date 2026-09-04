<?php

declare(strict_types=1);

/**
 * ResumableConfig.php
 *
 * PHP Version 8.4
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 */

namespace Blackcube\FileProvider\Resumable;

/**
 * Centralized configuration for Resumable.js upload.
 *
 * Usage in config/params.php:
 *   'blackcube/fileprovider' => [
 *       'resumable' => [
 *           'uploadEndpoint' => '/fileprovider/upload',
 *           'previewEndpoint' => '/fileprovider/preview',
 *           'deleteEndpoint' => '/fileprovider/delete',
 *       ],
 *   ],
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 */
class ResumableConfig
{
    public function __construct(
        private int $chunkSize = 524288,
        private ?string $uploadEndpoint = null,
        private ?string $previewEndpoint = null,
        private ?string $deleteEndpoint = null,
        private ?string $filetypeIconAlias = null,
        private int $thumbnailWidth = 200,
        private int $thumbnailHeight = 200,
    ) {
    }

    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    public function getUploadEndpoint(): string
    {
        if ($this->uploadEndpoint === null) {
            throw new \RuntimeException('uploadEndpoint is not configured. Set it in params.php under blackcube/fileprovider.resumable.');
        }
        return $this->uploadEndpoint;
    }

    public function getPreviewEndpoint(): string
    {
        if ($this->previewEndpoint === null) {
            throw new \RuntimeException('previewEndpoint is not configured. Set it in params.php under blackcube/fileprovider.resumable.');
        }
        return $this->previewEndpoint;
    }

    public function getDeleteEndpoint(): string
    {
        if ($this->deleteEndpoint === null) {
            throw new \RuntimeException('deleteEndpoint is not configured. Set it in params.php under blackcube/fileprovider.resumable.');
        }
        return $this->deleteEndpoint;
    }

    public function getFiletypeIconAlias(): string
    {
        return $this->filetypeIconAlias ?? dirname(__DIR__).'/resources/filetypes/';
    }

    public function getThumbnailWidth(): int
    {
        return $this->thumbnailWidth;
    }

    public function getThumbnailHeight(): int
    {
        return $this->thumbnailHeight;
    }

    /**
     * Clean a filename (allowed characters only, prevents path traversal).
     */
    public static function cleanFilename(string $filename): string
    {
        $filename = str_replace(['../', '..\\', '..'], '', $filename);
        return preg_replace('/[^a-z0-9_\-.]+/i', '_', $filename) ?? $filename;
    }
}
