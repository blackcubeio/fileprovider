<?php

declare(strict_types=1);

/**
 * ResumableService.php
 *
 * PHP Version 8.3+
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */

namespace Blackcube\FileProvider\Resumable;

use Blackcube\FileProvider\FileProvider;

/**
 * Dedicated service for chunked upload via Resumable.js.
 *
 * Encapsulates all upload logic: chunk storage, verification,
 * assembly and deletion. Uses FileProvider for all filesystem operations.
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */
final class ResumableService
{
    private const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif'];
    private const SVG_EXTENSIONS = ['svg'];

    public function __construct(
        private FileProvider $fileProvider,
        private ResumableConfig $config,
    ) {
    }

    /**
     * Check if a chunk exists (for resume).
     */
    public function chunkExists(string $identifier, string $filename, int $chunkNumber): bool
    {
        $chunkPath = $this->getChunkPath($identifier, $filename, $chunkNumber);
        return $this->fileProvider->fileExists($chunkPath);
    }

    /**
     * Save a chunk.
     *
     * @param resource $stream
     */
    public function saveChunk(
        string $identifier,
        string $filename,
        int $chunkNumber,
        mixed $stream
    ): void {
        $chunkDir = $this->getChunkDir($identifier);

        if (!$this->fileProvider->directoryExists($chunkDir)) {
            $this->fileProvider->createDirectory($chunkDir);
        }

        $chunkPath = $this->getChunkPath($identifier, $filename, $chunkNumber);
        $this->fileProvider->writeStream($chunkPath, $stream);
    }

    /**
     * Check if all chunks are uploaded.
     */
    public function isComplete(
        string $identifier,
        string $filename,
        int $totalChunks
    ): bool {
        $complete = $totalChunks > 0;

        for ($i = 1; $i <= $totalChunks && $complete; $i++) {
            $complete = $this->chunkExists($identifier, $filename, $i);
        }

        return $complete;
    }

    /**
     * Assemble chunks into final file.
     *
     * @return string Final filename (empty on failure)
     */
    public function assemble(string $identifier, string $filename): string
    {
        $chunkDir = $this->getChunkDir($identifier);
        $cleanFilename = ResumableConfig::cleanFilename($filename);
        $finalPath = $this->getTmpPrefix() . '/' . $cleanFilename;
        $result = '';

        $assembled = fopen('php://temp', 'r+');

        if ($assembled !== false) {
            $chunks = [];
            $listing = $this->fileProvider->listContents($chunkDir, false);
            foreach ($listing as $item) {
                if ($item->isFile() && str_contains($item->path(), '.part')) {
                    $chunks[] = $item->path();
                }
            }
            natsort($chunks);

            foreach ($chunks as $chunkPath) {
                $chunkStream = $this->fileProvider->readStream($chunkDir . '/' . basename($chunkPath));
                if (is_resource($chunkStream)) {
                    stream_copy_to_stream($chunkStream, $assembled);
                    fclose($chunkStream);
                }
            }
            rewind($assembled);

            $this->fileProvider->writeStream($finalPath, $assembled);
            fclose($assembled);

            $this->fileProvider->deleteDirectory($chunkDir);

            $result = $cleanFilename;
        }

        return $result;
    }

    /**
     * Delete a temporary file.
     *
     * @throws \InvalidArgumentException if file is not in @bltmp
     */
    public function deleteTmpFile(string $name): void
    {
        $tmpPrefix = $this->getTmpPrefix();

        if (!str_starts_with($name, $tmpPrefix . '/')) {
            throw new \InvalidArgumentException('Can only delete temporary files');
        }

        if ($this->fileProvider->fileExists($name)) {
            $this->fileProvider->delete($name);
        }
    }

    /**
     * Generate a preview (stream).
     *
     * @return array{stream: resource, mimeType: string, filename: string}|null
     */
    public function getPreview(string $name, bool $original = false): ?array
    {
        $result = null;

        $canHandle = $this->fileProvider->canHandle($name)
            && $this->fileProvider->fileExists($name);

        if ($canHandle) {
            $mimeType = $this->fileProvider->mimeType($name);
            $filename = basename($name);
            $needsThumbnail = !$original && $this->isImage($name);

            $stream = $needsThumbnail
                ? $this->fileProvider
                    ->resize($this->config->getThumbnailWidth(), $this->config->getThumbnailHeight())
                    ->readStream($name)
                : $this->fileProvider->readStream($name);

            $result = [
                'stream' => $stream,
                'mimeType' => $mimeType,
                'filename' => $filename,
            ];
        }

        return $result;
    }

    /**
     * Check if a file is an image.
     */
    public function isImage(string $name): bool
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        return in_array($extension, self::IMAGE_EXTENSIONS, true);
    }

    /**
     * Check if a file is an SVG.
     */
    public function isSvg(string $name): bool
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        return in_array($extension, self::SVG_EXTENSIONS, true);
    }

    /**
     * Get the configured temporary prefix.
     */
    public function getTmpPrefix(): string
    {
        return trim($this->config->getTmpPrefix(), '/');
    }

    /**
     * Get the chunk directory for an identifier.
     */
    private function getChunkDir(string $identifier): string
    {
        $cleanIdentifier = ResumableConfig::cleanFilename($identifier);
        return $this->getTmpPrefix() . '/' . $cleanIdentifier;
    }

    /**
     * Get the path of a chunk.
     */
    private function getChunkPath(string $identifier, string $filename, int $chunkNumber): string
    {
        return $this->getChunkDir($identifier) . '/' . $filename . '.part' . $chunkNumber;
    }
}