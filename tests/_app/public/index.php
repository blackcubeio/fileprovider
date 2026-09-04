<?php

declare(strict_types=1);

/**
 * index.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

/**
 * Mini test app for functional tests
 *
 * Routes:
 * - GET/POST /fileprovider/upload → ResumableUploadHandler
 * - GET /fileprovider/preview → ResumablePreviewHandler
 * - DELETE /fileprovider/delete → ResumableDeleteHandler
 */

use Blackcube\FileProvider\Handlers\ResumableDeleteHandler;
use Blackcube\FileProvider\Handlers\ResumablePreviewHandler;
use Blackcube\FileProvider\Handlers\ResumableUploadHandler;
use Blackcube\FileProvider\FileProvider;
use Blackcube\FileProvider\Flysystem\FlysystemAwsS3;
use Blackcube\FileProvider\Flysystem\FlysystemLocal;
use Blackcube\FileProvider\Resumable\ResumableConfig;
use Blackcube\FileProvider\Resumable\ResumableService;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Aliases\Aliases;
use Yiisoft\DataResponse\Formatter\JsonFormatter;
use Yiisoft\DataResponse\ResponseFactory\JsonResponseFactory;

require dirname(__DIR__, 3).'/vendor/autoload.php';

if (getenv('FILESYSTEM_TYPE') === false) {
    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 3));
    $dotenv->safeLoad();
}

$psr17Factory = new Psr17Factory();

$creator = new ServerRequestCreator(
    $psr17Factory,
    $psr17Factory,
    $psr17Factory,
    $psr17Factory
);
$request = $creator->fromGlobals();

$basePath = sys_get_temp_dir().'/fileprovider-functional-tests';

if (is_dir($basePath.'/bltmp') === false) {
    mkdir($basePath.'/bltmp', 0777, true);
}
if (is_dir($basePath.'/blfs') === false) {
    mkdir($basePath.'/blfs', 0777, true);
}

$filesystemType = getenv('FILESYSTEM_TYPE') ?: ($_ENV['FILESYSTEM_TYPE'] ?? 'local');

if ($filesystemType === 's3') {
    $s3Prefix = 'functional-tests';

    $tmpFs = new FlysystemAwsS3(
        bucket: $_ENV['FILESYSTEM_S3_BUCKET'] ?? 'testing',
        key: $_ENV['FILESYSTEM_S3_KEY'] ?? '',
        secret: $_ENV['FILESYSTEM_S3_SECRET'] ?? '',
        region: $_ENV['FILESYSTEM_S3_REGION'] ?? 'eu-east-1',
        endpoint: $_ENV['FILESYSTEM_S3_ENDPOINT'] ?? null,
        prefix: $s3Prefix.'/bltmp',
        pathStyleEndpoint: (bool) ($_ENV['FILESYSTEM_S3_PATH_STYLE'] ?? false),
    );

    $storageFs = new FlysystemAwsS3(
        bucket: $_ENV['FILESYSTEM_S3_BUCKET'] ?? 'testing',
        key: $_ENV['FILESYSTEM_S3_KEY'] ?? '',
        secret: $_ENV['FILESYSTEM_S3_SECRET'] ?? '',
        region: $_ENV['FILESYSTEM_S3_REGION'] ?? 'eu-east-1',
        endpoint: $_ENV['FILESYSTEM_S3_ENDPOINT'] ?? null,
        prefix: $s3Prefix.'/blfs',
        pathStyleEndpoint: (bool) ($_ENV['FILESYSTEM_S3_PATH_STYLE'] ?? false),
    );
} else {
    $tmpFs = new FlysystemLocal($basePath.'/bltmp');
    $storageFs = new FlysystemLocal($basePath.'/blfs');
}

$aliases = new Aliases();

$fileProvider = new FileProvider($aliases);
$fileProvider->addFilesystem('@bltmp', $tmpFs);
$fileProvider->addFilesystem('@blfs', $storageFs);

$resumableConfig = new ResumableConfig(
    chunkSize: 524288,
    uploadEndpoint: '/fileprovider/upload',
    previewEndpoint: '/fileprovider/preview',
    deleteEndpoint: '/fileprovider/delete',
    thumbnailWidth: 200,
    thumbnailHeight: 200,
);

$resumableService = new ResumableService($fileProvider, $resumableConfig);

$jsonResponseFactory = new JsonResponseFactory($psr17Factory, new JsonFormatter());

$path = $request->getUri()->getPath();
$method = $request->getMethod();

$response = match (true) {
    str_ends_with($path, '/fileprovider/upload') === true => (new ResumableUploadHandler(
        $psr17Factory,
        $jsonResponseFactory,
        $resumableService,
        $resumableConfig,
    ))->handle($request),

    str_ends_with($path, '/fileprovider/preview') === true => (new ResumablePreviewHandler(
        $psr17Factory,
        $psr17Factory,
        $resumableService,
        $resumableConfig,
        $aliases,
    ))->handle($request),

    str_ends_with($path, '/fileprovider/delete') === true => (new ResumableDeleteHandler(
        $psr17Factory,
        $resumableService,
    ))->handle($request),

    default => $psr17Factory->createResponse(404)
        ->withBody($psr17Factory->createStream('Not Found')),
};

emitResponse($response);

/**
 * Emit PSR-7 response
 */
function emitResponse(ResponseInterface $response): void
{
    http_response_code($response->getStatusCode());

    foreach ($response->getHeaders() as $name => $values) {
        foreach ($values as $value) {
            header($name.': '.$value, false);
        }
    }

    $body = $response->getBody();
    if ($body->isSeekable() === true) {
        $body->rewind();
    }

    while ($body->eof() === false) {
        echo $body->read(8192);
    }
}
