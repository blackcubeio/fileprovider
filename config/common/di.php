<?php

declare(strict_types=1);

/**
 * di.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

use Blackcube\FileProvider\CacheFile;
use Blackcube\FileProvider\FileProvider;
use Blackcube\FileProvider\Interfaces\FileProviderInterface;
use Blackcube\FileProvider\Resumable\ResumableConfig;

/** @var array $params */

return [
    FileProviderInterface::class => [
        'class' => FileProvider::class,
        '__construct()' => [
            'filesystems' => $params['blackcube/fileprovider']['filesystems'] ?? [],
            'defaultAlias' => $params['blackcube/fileprovider']['defaultAlias'] ?? FileProvider::ALIAS_FS,
            'tempAlias' => $params['blackcube/fileprovider']['tempAlias'] ?? FileProvider::ALIAS_TMP,
            'imageDriver' => $params['blackcube/fileprovider']['imageDriver'] ?? null,
        ],
    ],


    ResumableConfig::class => [
        'class' => ResumableConfig::class,
        '__construct()' => [
            'chunkSize' => $params['blackcube/fileprovider']['resumable']['chunkSize'] ?? 524288,
            'uploadEndpoint' => $params['blackcube/fileprovider']['resumable']['uploadEndpoint'] ?? null,
            'previewEndpoint' => $params['blackcube/fileprovider']['resumable']['previewEndpoint'] ?? null,
            'deleteEndpoint' => $params['blackcube/fileprovider']['resumable']['deleteEndpoint'] ?? null,
            'filetypeIconAlias' => $params['blackcube/fileprovider']['resumable']['filetypeIconAlias'] ?? null,
            'thumbnailWidth' => $params['blackcube/fileprovider']['resumable']['thumbnailWidth'] ?? 200,
            'thumbnailHeight' => $params['blackcube/fileprovider']['resumable']['thumbnailHeight'] ?? 200,
        ],
    ],

    CacheFile::class => [
        'class' => CacheFile::class,
        '__construct()' => [
            'cachePath' => $params['blackcube/fileprovider']['cache']['path'] ?? null,
            'cacheUrl' => $params['blackcube/fileprovider']['cache']['url'] ?? null,
        ],
    ],
];
