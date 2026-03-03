<?php

declare(strict_types=1);

/**
 * ResumableConfig.php
 *
 * PHP Version 8.3+
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */

namespace Blackcube\FileProvider\Resumable;

/**
 * Centralized configuration for Resumable.js upload.
 *
 * Usage in config/params.php:
 *   'blackcube/fileprovider' => [
 *       'resumable' => [
 *           'tmpPrefix' => '@bltmp',
 *           'chunkSize' => 524288,
 *           'uploadEndpoint' => '@web/fileprovider/upload',
 *           'previewEndpoint' => '@web/fileprovider/preview',
 *           'deleteEndpoint' => '@web/fileprovider/delete',
 *       ],
 *   ],
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */
final class ResumableConfig
{
    private string $tmpPrefix;
    private int $chunkSize;
    private string $uploadEndpoint;
    private string $previewEndpoint;
    private string $deleteEndpoint;
    private string $filetypeIconAlias;
    private int $thumbnailWidth;
    private int $thumbnailHeight;

    public function __construct(
        ?string $tmpPrefix = null,
        ?int $chunkSize = null,
        ?string $uploadEndpoint = null,
        ?string $previewEndpoint = null,
        ?string $deleteEndpoint = null,
        ?string $filetypeIconAlias = null,
        ?int $thumbnailWidth = null,
        ?int $thumbnailHeight = null,
    ) {
        $this->tmpPrefix = $tmpPrefix ?? '@bltmp';
        $this->chunkSize = $chunkSize ?? 524288;
        $this->uploadEndpoint = $uploadEndpoint ?? '/fileprovider/upload';
        $this->previewEndpoint = $previewEndpoint ?? '/fileprovider/preview';
        $this->deleteEndpoint = $deleteEndpoint ?? '/fileprovider/delete';
        $this->filetypeIconAlias = $filetypeIconAlias ?? '@fileprovider/filetypes/';
        $this->thumbnailWidth = $thumbnailWidth ?? 200;
        $this->thumbnailHeight = $thumbnailHeight ?? 200;
    }

    public function getTmpPrefix(): string
    {
        return $this->tmpPrefix;
    }

    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    public function getUploadEndpoint(): string
    {
        return $this->uploadEndpoint;
    }

    public function getPreviewEndpoint(): string
    {
        return $this->previewEndpoint;
    }

    public function getDeleteEndpoint(): string
    {
        return $this->deleteEndpoint;
    }

    public function getFiletypeIconAlias(): string
    {
        return $this->filetypeIconAlias;
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
     * Create an instance from configuration array.
     */
    public static function fromArray(array $config): self
    {
        return new self(
            $config['tmpPrefix'] ?? null,
            $config['chunkSize'] ?? null,
            $config['uploadEndpoint'] ?? null,
            $config['previewEndpoint'] ?? null,
            $config['deleteEndpoint'] ?? null,
            $config['filetypeIconAlias'] ?? null,
            $config['thumbnailWidth'] ?? null,
            $config['thumbnailHeight'] ?? null,
        );
    }

    /**
     * Clean a filename (allowed characters only, prevents path traversal).
     */
    public static function cleanFilename(string $filename): string
    {
        // First, remove path traversal sequences
        $filename = str_replace(['../', '..\\', '..'], '', $filename);
        // Then, keep only safe characters
        return preg_replace('/[^a-z0-9_\-.]+/i', '_', $filename) ?? $filename;
    }
}
