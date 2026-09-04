<?php

declare(strict_types=1);

/**
 * ResumableUploadHandler.php
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
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\DataResponse\ResponseFactory\JsonResponseFactory;
use Yiisoft\Http\Method;
use Yiisoft\Http\Status;

/**
 * Handler for file upload via Resumable.js.
 *
 * Handles:
 * - GET: test if a chunk exists (for resume)
 * - POST: upload a chunk
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 */
class ResumableUploadHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly JsonResponseFactory $jsonResponseFactory,
        private readonly ResumableService $resumableService,
        private readonly ResumableConfig $config,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() === Method::GET) {
            return $this->handleTestChunk($request);
        }

        return $this->handleUploadChunk($request);
    }

    private function handleTestChunk(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();

        $identifier = $this->getResumableParam($params, 'identifier');
        $filename = $this->getResumableParam($params, 'filename');
        $chunkNumber = $this->getResumableParam($params, 'chunkNumber');

        if ($identifier === null || $filename === null || $chunkNumber === null) {
            return $this->responseFactory->createResponse(Status::BAD_REQUEST);
        }

        $filename = $this->config->cleanFilename($filename);

        if ($this->resumableService->chunkExists($identifier, $filename, (int) $chunkNumber) === true) {
            return $this->responseFactory->createResponse(Status::OK);
        }

        return $this->responseFactory->createResponse(Status::NO_CONTENT);
    }

    private function handleUploadChunk(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getParsedBody();
        if (is_array($params) === false) {
            return $this->responseFactory->createResponse(Status::BAD_REQUEST);
        }

        $uploadedFiles = $request->getUploadedFiles();

        $identifier = $this->getResumableParam($params, 'identifier');
        $filename = $this->getResumableParam($params, 'filename');
        $chunkNumber = $this->getResumableParam($params, 'chunkNumber');
        $totalChunks = $this->getResumableParam($params, 'totalChunks');

        if ($identifier === null || $filename === null || $chunkNumber === null || $totalChunks === null) {
            return $this->responseFactory->createResponse(Status::BAD_REQUEST);
        }

        $filename = $this->config->cleanFilename($filename);

        $file = $uploadedFiles['file'] ?? null;
        if ($file === null) {
            return $this->responseFactory->createResponse(Status::BAD_REQUEST);
        }

        if ($this->resumableService->chunkExists($identifier, $filename, (int) $chunkNumber) === false) {
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

        $finalFilename = null;
        if ($this->resumableService->isComplete($identifier, $filename, (int) $totalChunks) === true) {
            $finalFilename = $this->resumableService->assemble($identifier, $filename);
        }

        return $this->jsonResponseFactory->createResponse([
            'complete' => $finalFilename !== null && $finalFilename !== '',
            'finalFilename' => $finalFilename,
        ]);
    }

    private function getResumableParam(array $params, string $name): ?string
    {
        $value = $params['resumable'.ucfirst($name)] ?? null;
        return is_string($value) === true ? $value : null;
    }
}
