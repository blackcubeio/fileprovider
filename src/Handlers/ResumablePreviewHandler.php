<?php

declare(strict_types=1);

/**
 * ResumablePreviewHandler.php
 *
 * PHP Version 8.4
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 */

namespace Blackcube\FileProvider\Handlers;

use Blackcube\FileProvider\Resumable\ResumableConfig;
use Blackcube\FileProvider\Resumable\ResumableService;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Http\Status;

/**
 * Handler for uploaded file previews.
 *
 * Handles:
 * - @bltmp/...: temporary files
 * - @blfs/..., @blcdn/..., etc.: final files (any FileProvider alias)
 *
 * For images: generates a thumbnail or direct stream
 * For others: returns an icon based on extension
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 */
class ResumablePreviewHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly ResumableService $resumableService,
        private readonly ResumableConfig $config,
        private readonly Aliases $aliases,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $name = $params['name'] ?? null;
        $original = ($params['original'] ?? '0') === '1';

        if ($name === null || $name === '') {
            return $this->responseFactory->createResponse(Status::BAD_REQUEST);
        }

        if ($this->resumableService->isSvg($name) === true) {
            $preview = $this->resumableService->getPreview($name, true);
            if ($preview === null) {
                return $this->responseFactory->createResponse(Status::NOT_FOUND);
            }
            return $this->streamResponse($preview, 'image/svg+xml');
        }

        if ($this->resumableService->isImage($name) === true) {
            $preview = $this->resumableService->getPreview($name, $original);
            if ($preview === null) {
                return $this->responseFactory->createResponse(Status::NOT_FOUND);
            }
            return $this->streamResponse($preview);
        }

        if ($this->resumableService->fileExists($name) === true) {
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
            ->withHeader('Content-Disposition', 'inline; filename="'.$preview['filename'].'"')
            ->withBody($stream);
    }

    private function streamIcon(string $extension): ResponseInterface
    {
        $iconAlias = $this->config->getFiletypeIconAlias().$extension.'.png';
        $iconPath = $this->aliases->get($iconAlias);

        if (file_exists($iconPath) === false) {
            $iconPath = $this->aliases->get($this->config->getFiletypeIconAlias().'file.png');
        }

        if (file_exists($iconPath) === false) {
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
