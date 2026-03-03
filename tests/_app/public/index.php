<?php

declare(strict_types=1);

/**
 * Mini test app for functional tests
 *
 * Routes:
 * - GET/POST /fileprovider/upload → ResumableUploadAction
 * - GET /fileprovider/preview → ResumablePreviewAction
 * - DELETE /fileprovider/delete → ResumableDeleteAction
 */

use Blackcube\FileProvider\Action\ResumableDeleteAction;
use Blackcube\FileProvider\Action\ResumablePreviewAction;
use Blackcube\FileProvider\Action\ResumableUploadAction;
use Blackcube\FileProvider\FileProvider;
use Blackcube\FileProvider\FlysystemAwsS3;
use Blackcube\FileProvider\FlysystemLocal;
use Blackcube\FileProvider\Resumable\ResumableConfig;
use Blackcube\FileProvider\Resumable\ResumableService;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Aliases\Aliases;
use Yiisoft\DataResponse\DataResponseFactory;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

// Load .env only if not running in test mode with explicit FILESYSTEM_TYPE
if (getenv('FILESYSTEM_TYPE') === false) {
    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 3));
    $dotenv->safeLoad();
}

// Create PSR-17 factories
$psr17Factory = new Psr17Factory();

// Create ServerRequest from globals
$creator = new ServerRequestCreator(
    $psr17Factory,
    $psr17Factory,
    $psr17Factory,
    $psr17Factory
);
$request = $creator->fromGlobals();

// Use a fixed base path for functional tests (same directory for all requests)
$basePath = sys_get_temp_dir() . '/fileprovider-functional-tests';

// Ensure directories exist
if (!is_dir($basePath . '/bltmp')) {
    mkdir($basePath . '/bltmp', 0777, true);
}
if (!is_dir($basePath . '/blfs')) {
    mkdir($basePath . '/blfs', 0777, true);
}

// Create FileProvider based on FILESYSTEM_TYPE (check getenv first, then $_ENV)
$filesystemType = getenv('FILESYSTEM_TYPE') ?: ($_ENV['FILESYSTEM_TYPE'] ?? 'local');

if ($filesystemType === 's3') {
    $s3Prefix = 'functional-tests';

    $tmpFs = new FlysystemAwsS3(
        bucket: $_ENV['FILESYSTEM_S3_BUCKET'] ?? 'testing',
        key: $_ENV['FILESYSTEM_S3_KEY'] ?? '',
        secret: $_ENV['FILESYSTEM_S3_SECRET'] ?? '',
        region: $_ENV['FILESYSTEM_S3_REGION'] ?? 'eu-east-1',
        endpoint: $_ENV['FILESYSTEM_S3_ENDPOINT'] ?? null,
        prefix: $s3Prefix . '/bltmp',
        pathStyleEndpoint: (bool) ($_ENV['FILESYSTEM_S3_PATH_STYLE'] ?? false),
    );

    $storageFs = new FlysystemAwsS3(
        bucket: $_ENV['FILESYSTEM_S3_BUCKET'] ?? 'testing',
        key: $_ENV['FILESYSTEM_S3_KEY'] ?? '',
        secret: $_ENV['FILESYSTEM_S3_SECRET'] ?? '',
        region: $_ENV['FILESYSTEM_S3_REGION'] ?? 'eu-east-1',
        endpoint: $_ENV['FILESYSTEM_S3_ENDPOINT'] ?? null,
        prefix: $s3Prefix . '/blfs',
        pathStyleEndpoint: (bool) ($_ENV['FILESYSTEM_S3_PATH_STYLE'] ?? false),
    );
} else {
    $tmpFs = new FlysystemLocal($basePath . '/bltmp');
    $storageFs = new FlysystemLocal($basePath . '/blfs');
}

$fileProvider = new FileProvider([
    '@bltmp' => $tmpFs,
    '@blfs' => $storageFs,
]);

// Create ResumableConfig
$resumableConfig = new ResumableConfig(
    tmpPrefix: '@bltmp',
    chunkSize: 524288,
    uploadEndpoint: '/fileprovider/upload',
    previewEndpoint: '/fileprovider/preview',
    deleteEndpoint: '/fileprovider/delete',
    filetypeIconAlias: '@fileprovider/filetypes/',
    thumbnailWidth: 200,
    thumbnailHeight: 200,
);

// Create ResumableService
$resumableService = new ResumableService($fileProvider, $resumableConfig);

// Create Aliases for icon resolution
$aliases = new Aliases([
    '@fileprovider' => dirname(__DIR__, 3) . '/src/resources',
]);

// Create DataResponseFactory (requires both ResponseFactoryInterface and StreamFactoryInterface)
$dataResponseFactory = new DataResponseFactory($psr17Factory, $psr17Factory);

// Route the request
$path = $request->getUri()->getPath();
$method = $request->getMethod();

$response = match (true) {
    // Upload endpoint
    str_ends_with($path, '/fileprovider/upload') => (new ResumableUploadAction(
        $dataResponseFactory,
        $resumableService,
        $resumableConfig,
    ))($request),

    // Preview endpoint
    str_ends_with($path, '/fileprovider/preview') => (new ResumablePreviewAction(
        $psr17Factory,
        $psr17Factory,
        $resumableService,
        $resumableConfig,
        $aliases,
    ))($request),

    // Delete endpoint
    str_ends_with($path, '/fileprovider/delete') => (new ResumableDeleteAction(
        $dataResponseFactory,
        $resumableService,
    ))($request),

    // 404 for unknown routes
    default => $psr17Factory->createResponse(404)
        ->withBody($psr17Factory->createStream('Not Found')),
};

// Emit response
emitResponse($response);

/**
 * Emit PSR-7 response
 */
function emitResponse(ResponseInterface $response): void
{
    // Status line
    http_response_code($response->getStatusCode());

    // Headers
    foreach ($response->getHeaders() as $name => $values) {
        foreach ($values as $value) {
            header("{$name}: {$value}", false);
        }
    }

    // Body
    $body = $response->getBody();
    if ($body->isSeekable()) {
        $body->rewind();
    }

    while (!$body->eof()) {
        echo $body->read(8192);
    }
}
