<?php

declare(strict_types=1);

/**
 * ResumableDeleteAction.php
 *
 * PHP Version 8.3+
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */

namespace Blackcube\FileProvider\Action;

use Blackcube\FileProvider\Resumable\ResumableService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

/**
 * Action for temporary file deletion.
 *
 * Deletes ONLY files in @bltmp (temporary).
 * Files in @blfs (Flysystem) are NOT deleted here — this is the responsibility
 * of business logic (Blams).
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */
final class ResumableDeleteAction
{
    public function __construct(
        private DataResponseFactoryInterface $responseFactory,
        private ResumableService $resumableService,
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        // Resumable.js sends delete URL with name in query string
        $params = $request->getQueryParams();
        $name = $params['name'] ?? null;

        if ($name === null || $name === '') {
            return $this->responseFactory->createResponse()
                ->withStatus(Status::BAD_REQUEST);
        }

        try {
            $this->resumableService->deleteTmpFile($name);
            return $this->responseFactory->createResponse()
                ->withStatus(Status::NO_CONTENT);
        } catch (\InvalidArgumentException|\League\Flysystem\PathTraversalDetected) {
            return $this->responseFactory->createResponse()
                ->withStatus(Status::FORBIDDEN);
        }
    }
}
