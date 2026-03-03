<?php

declare(strict_types=1);

/**
 * FlysystemAwsS3.php
 *
 * PHP Version 8.3+
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */

namespace Blackcube\FileProvider;

use Aws\S3\S3Client;
use Blackcube\FileProvider\Exception\InvalidConfigurationException;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\AwsS3V3\VisibilityConverter;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathNormalizer;
use League\MimeTypeDetection\MimeTypeDetector;

/**
 * AWS S3 / S3-compatible (MinIO) adapter.
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */
class FlysystemAwsS3 extends Flysystem
{
    private ?S3Client $client = null;

    /**
     * @param string $bucket S3 bucket name
     * @param string|null $key AWS access key (required if credentials not provided)
     * @param string|null $secret AWS secret key (required if credentials not provided)
     * @param string|null $region AWS region
     * @param string|null $endpoint Custom endpoint for S3-compatible services (MinIO)
     * @param string|null $baseUrl Base URL for the bucket
     * @param string $prefix Path prefix within the bucket
     * @param bool $pathStyleEndpoint Use path-style endpoint (required for MinIO)
     * @param string $version AWS API version
     * @param array|\Aws\CacheInterface|\Aws\Credentials\CredentialsInterface|bool|callable|null $credentials Advanced credentials configuration
     * @param VisibilityConverter|null $visibility Visibility converter
     * @param MimeTypeDetector|null $mimeTypeDetector MIME type detector
     * @param array $options Additional S3 options
     * @param bool $streamReads Enable streaming reads
     * @param array $config Flysystem config
     * @param PathNormalizer|null $pathNormalizer Path normalizer
     */
    public function __construct(
        private readonly string $bucket,
        private readonly ?string $key = null,
        private readonly ?string $secret = null,
        private readonly ?string $region = null,
        private readonly ?string $endpoint = null,
        private readonly ?string $baseUrl = null,
        private readonly string $prefix = '',
        private readonly bool $pathStyleEndpoint = false,
        private readonly string $version = 'latest',
        private readonly mixed $credentials = null,
        private readonly ?VisibilityConverter $visibility = null,
        private readonly ?MimeTypeDetector $mimeTypeDetector = null,
        private readonly array $options = [],
        private readonly bool $streamReads = true,
        array $config = [],
        ?PathNormalizer $pathNormalizer = null,
    ) {
        parent::__construct($config, $pathNormalizer);
    }


    /**
     * @inheritdoc
     */
    public function deleteDirectory(string $location): void
    {
        // Delete contents via Flysystem
        foreach ($this->listContents($location, true) as $item) {
            if ($item->isFile()) {
                $this->delete($item->path());
            }
        }

        // Delete placeholder (S3 deleteObject is idempotent)
        $this->deletePlaceholder($location);
    }

    /**
     * Delete a directory placeholder (S3 object ending with /).
     * Flysystem can't delete these because it normalizes paths and strips trailing slashes.
     */
    private function deletePlaceholder(string $path): void
    {
        $key = $this->prefix;
        if ($key !== '') {
            $key .= '/';
        }
        $key .= rtrim($path, '/') . '/';

        $this->client->deleteObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
        ]);
    }

    protected function prepareAdapter(): FilesystemAdapter
    {
        if ($this->credentials === null && ($this->key === null || $this->secret === null)) {
            throw new InvalidConfigurationException('Either credentials or key/secret pair must be provided');
        }

        $clientConfig = [
            'version' => $this->version,
        ];

        if ($this->credentials !== null) {
            $clientConfig['credentials'] = $this->credentials;
        } else {
            $clientConfig['credentials'] = [
                'key' => $this->key,
                'secret' => $this->secret,
            ];
        }

        if ($this->pathStyleEndpoint) {
            $clientConfig['use_path_style_endpoint'] = true;
        }

        if ($this->region !== null) {
            $clientConfig['region'] = $this->region;
        }

        if ($this->baseUrl !== null) {
            $clientConfig['base_url'] = $this->baseUrl;
        }

        if ($this->endpoint !== null) {
            $clientConfig['endpoint'] = $this->endpoint;
        }

        $this->client = new S3Client($clientConfig);

        return new AwsS3V3Adapter(
            $this->client,
            $this->bucket,
            $this->prefix,
            $this->visibility,
            $this->mimeTypeDetector,
            $this->options,
            $this->streamReads,
        );
    }
}
