<?php

declare(strict_types=1);

/**
 * ResumableDeleteHandler.php
 *
 * PHP Version 8.4
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 */

namespace Blackcube\FileProvider\Handlers;

use Blackcube\FileProvider\Resumable\ResumableService;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Http\Status;

/**
 * Handler for temporary file deletion.
 *
 * Deletes ONLY files in @bltmp (temporary).
 * Files in @blfs (Flysystem) are NOT deleted here — this is the responsibility
 * of business logic (Blams).
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 */
class ResumableDeleteHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly ResumableService $resumableService,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $name = $params['name'] ?? null;

        if ($name === null || $name === '') {
            return $this->responseFactory->createResponse(Status::BAD_REQUEST);
        }

        try {
            $this->resumableService->deleteTmpFile($name);
            return $this->responseFactory->createResponse(Status::NO_CONTENT);
        } catch (\InvalidArgumentException|\League\Flysystem\PathTraversalDetected) {
            return $this->responseFactory->createResponse(Status::FORBIDDEN);
        }
    }
}
