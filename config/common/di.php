<?php

declare(strict_types=1);

/**
 * di.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

use Blackcube\FileProvider\Contracts\FileProviderInterface;
use Blackcube\FileProvider\FileProvider;
use Blackcube\FileProvider\FlysystemAwsS3;
use Blackcube\FileProvider\FlysystemFtp;
use Blackcube\FileProvider\FlysystemLocal;
use Blackcube\FileProvider\FlysystemProviderInterface;
use Blackcube\FileProvider\FlysystemSftp;
use Blackcube\FileProvider\Resumable\ResumableConfig;
use Blackcube\FileProvider\Resumable\ResumableService;
use Yiisoft\Aliases\Aliases;

/** @var array $params */

return [
    FileProviderInterface::class => static function (Aliases $aliases) use ($params): FileProviderInterface {
        $config = $params['blackcube/fileprovider'];
        $filesystems = [];

        foreach ($config['filesystems'] as $alias => $fsConfig) {
            $filesystems[$alias] = match ($fsConfig['type']) {
                'local' => new FlysystemLocal(
                    path: $aliases->get($fsConfig['path']),
                ),
                's3' => new FlysystemAwsS3(
                    bucket: $fsConfig['bucket'],
                    key: $fsConfig['key'] ?? null,
                    secret: $fsConfig['secret'] ?? null,
                    region: $fsConfig['region'] ?? null,
                    endpoint: $fsConfig['endpoint'] ?? null,
                    prefix: $fsConfig['prefix'] ?? '',
                    pathStyleEndpoint: $fsConfig['pathStyleEndpoint'] ?? false,
                ),
                'ftp' => new FlysystemFtp(
                    host: $fsConfig['host'],
                    username: $fsConfig['username'],
                    password: $fsConfig['password'],
                    root: $fsConfig['root'] ?? '/',
                    port: $fsConfig['port'] ?? 21,
                    ssl: $fsConfig['ssl'] ?? false,
                ),
                'sftp' => new FlysystemSftp(
                    host: $fsConfig['host'],
                    username: $fsConfig['username'],
                    password: $fsConfig['password'] ?? null,
                    privateKey: $fsConfig['privateKey'] ?? null,
                    root: $fsConfig['root'] ?? '/',
                    port: $fsConfig['port'] ?? 22,
                ),
                default => throw new \InvalidArgumentException(
                    "Unknown filesystem type: {$fsConfig['type']}"
                ),
            };
        }

        return new FileProvider(
            filesystems: $filesystems,
            defaultAlias: $config['defaultAlias'],
            imageDriver: $config['imageDriver'],
        );
    },

    ResumableConfig::class => static function (Aliases $aliases) use ($params): ResumableConfig {
        $config = $params['blackcube/fileprovider']['resumable'] ?? [];

        // Resolve @web alias for endpoints
        if (isset($config['uploadEndpoint'])) {
            $config['uploadEndpoint'] = $aliases->get($config['uploadEndpoint']);
        }
        if (isset($config['previewEndpoint'])) {
            $config['previewEndpoint'] = $aliases->get($config['previewEndpoint']);
        }
        if (isset($config['deleteEndpoint'])) {
            $config['deleteEndpoint'] = $aliases->get($config['deleteEndpoint']);
        }

        return ResumableConfig::fromArray($config);
    },

    ResumableService::class => static function (
        FileProviderInterface $fileProvider,
        ResumableConfig $config
    ): ResumableService {
        /** @var FileProvider $fileProvider */
        return new ResumableService($fileProvider, $config);
    },
];
