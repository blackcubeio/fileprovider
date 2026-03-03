<?php

declare(strict_types=1);

/**
 * ResumableUploadAction.php
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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\DataResponseFactoryInterface;
use Yiisoft\DataResponse\Formatter\JsonDataResponseFormatter;
use Yiisoft\Http\Method;
use Yiisoft\Http\Status;

/**
 * Action for file upload via Resumable.js.
 *
 * Handles:
 * - GET: test if a chunk exists (for resume)
 * - POST: upload a chunk
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */
final class ResumableUploadAction
{
    public function __construct(
        private DataResponseFactoryInterface $responseFactory,
        private ResumableService $resumableService,
        private ResumableConfig $config,
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() === Method::GET) {
            return $this->handleTestChunk($request);
        }

        return $this->handleUploadChunk($request);
    }

    /**
     * GET: test if a chunk exists (for resume).
     */
    private function handleTestChunk(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();

        $identifier = $this->getResumableParam($params, 'identifier');
        $filename = $this->getResumableParam($params, 'filename');
        $chunkNumber = $this->getResumableParam($params, 'chunkNumber');

        if ($identifier === null || $filename === null || $chunkNumber === null) {
            return $this->responseFactory->createResponse()
                ->withStatus(Status::BAD_REQUEST);
        }

        // Sanitize filename to prevent path traversal
        $filename = $this->config->cleanFilename($filename);

        if ($this->resumableService->chunkExists($identifier, $filename, (int) $chunkNumber)) {
            return $this->responseFactory->createResponse()
                ->withStatus(Status::OK);
        }

        return $this->responseFactory->createResponse()
            ->withStatus(Status::NO_CONTENT);
    }

    /**
     * POST: upload a chunk.
     */
    private function handleUploadChunk(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getParsedBody();
        $uploadedFiles = $request->getUploadedFiles();

        $identifier = $this->getResumableParam($params, 'identifier');
        $filename = $this->getResumableParam($params, 'filename');
        $chunkNumber = $this->getResumableParam($params, 'chunkNumber');
        $totalChunks = $this->getResumableParam($params, 'totalChunks');

        if ($identifier === null || $filename === null || $chunkNumber === null || $totalChunks === null) {
            return $this->responseFactory->createResponse()
                ->withStatus(Status::BAD_REQUEST);
        }

        // Sanitize filename to prevent path traversal
        $filename = $this->config->cleanFilename($filename);

        // Gets the uploaded file
        $file = $uploadedFiles['file'] ?? null;
        if ($file === null) {
            return $this->responseFactory->createResponse()
                ->withStatus(Status::BAD_REQUEST);
        }

        // Saves chunk if not already exists
        if (!$this->resumableService->chunkExists($identifier, $filename, (int) $chunkNumber)) {
            $stream = $file->getStream()->detach();
            if ($stream !== null) {
                $this->resumableService->saveChunk(
                    $identifier,
                    $filename,
                    (int) $chunkNumber,
                    $stream
                );
            }
        }

        // Verifies if upload is complete
        $finalFilename = null;
        if ($this->resumableService->isComplete($identifier, $filename, (int) $totalChunks)) {
            $finalFilename = $this->resumableService->assemble($identifier, $filename);
        }

        return $this->responseFactory->createResponse([
            'complete' => $finalFilename !== null && $finalFilename !== '',
            'finalFilename' => $finalFilename,
        ])->withResponseFormatter(new JsonDataResponseFormatter());
    }

    /**
     * Get a Resumable.js parameter.
     */
    private function getResumableParam(array $params, string $name): ?string
    {
        $paramName = 'resumable' . ucfirst($name);
        return $params[$paramName] ?? null;
    }
}
