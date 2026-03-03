<?php

declare(strict_types=1);

/**
 * ResumablePreviewAction.php
 *
 * PHP Version 8.3+
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */

namespace Blackcube\FileProvider\Action;

use Blackcube\FileProvider\Resumable\ResumableConfig;
use Blackcube\FileProvider\Resumable\ResumableService;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Http\Status;

/**
 * Action for uploaded file previews.
 *
 * Handles:
 * - @bltmp/...: temporary files
 * - @blfs/..., @blcdn/..., etc.: final files (any FileProvider alias)
 *
 * For images: generates a thumbnail or direct stream
 * For others: returns an icon based on extension
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */
final class ResumablePreviewAction
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        private ResumableService $resumableService,
        private ResumableConfig $config,
        private Aliases $aliases,
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $name = $params['name'] ?? null;
        $original = ($params['original'] ?? '0') === '1';

        if ($name === null || $name === '') {
            return $this->responseFactory->createResponse(Status::BAD_REQUEST);
        }

        // SVG: direct stream (no thumbnail)
        if ($this->resumableService->isSvg($name)) {
            $preview = $this->resumableService->getPreview($name, true);
            if ($preview === null) {
                return $this->responseFactory->createResponse(Status::NOT_FOUND);
            }
            return $this->streamResponse($preview, 'image/svg+xml');
        }

        // Image: preview or original
        if ($this->resumableService->isImage($name)) {
            $preview = $this->resumableService->getPreview($name, $original);
            if ($preview === null) {
                return $this->responseFactory->createResponse(Status::NOT_FOUND);
            }
            return $this->streamResponse($preview);
        }

        // Non-image: icon
        $preview = $this->resumableService->getPreview($name, true);
        if ($preview !== null) {
            // File exists, return an icon
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            return $this->streamIcon($extension);
        }

        return $this->responseFactory->createResponse(Status::NOT_FOUND);
    }

    /**
     * Stream a response with the preview.
     *
     * @param array{stream: resource, mimeType: string, filename: string} $preview
     */
    private function streamResponse(array $preview, ?string $forceMimeType = null): ResponseInterface
    {
        $stream = $this->streamFactory->createStreamFromResource($preview['stream']);

        return $this->responseFactory->createResponse(Status::OK)
            ->withHeader('Content-Type', $forceMimeType ?? $preview['mimeType'])
            ->withHeader('Content-Disposition', 'inline; filename="' . $preview['filename'] . '"')
            ->withBody($stream);
    }

    /**
     * Stream an icon based on extension.
     */
    private function streamIcon(string $extension): ResponseInterface
    {
        $iconAlias = $this->config->getFiletypeIconAlias() . $extension . '.png';
        $iconPath = $this->aliases->get($iconAlias);

        if (!file_exists($iconPath)) {
            // Default icon
            $iconPath = $this->aliases->get($this->config->getFiletypeIconAlias() . 'file.png');
        }

        if (!file_exists($iconPath)) {
            return $this->responseFactory->createResponse(Status::NOT_FOUND);
        }

        $stream = $this->streamFactory->createStreamFromFile($iconPath, 'r');

        return $this->responseFactory->createResponse(Status::OK)
            ->withHeader('Content-Type', 'image/png')
            ->withHeader('Content-Disposition', 'inline; filename="icon.png"')
            ->withHeader('Content-Length', (string) filesize($iconPath))
            ->withBody($stream);
    }
}
