<?php

declare(strict_types=1);

/**
 * params.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

use Blackcube\FileProvider\FileProvider;

return [
    'blackcube/fileprovider' => [
        'filesystems' => [
            FileProvider::ALIAS_FS => [
                'type' => 'local',
                'path' => '@runtime/uploads',
            ],
            FileProvider::ALIAS_TMP => [
                'type' => 'local',
                'path' => '@runtime/tmp',
            ],
        ],
        'defaultAlias' => FileProvider::ALIAS_FS,
        'tempAlias' => FileProvider::ALIAS_TMP,
        'imageDriver' => null,
        'resumable' => [
            'chunkSize' => null,
            'uploadEndpoint' => null,
            'previewEndpoint' => null,
            'deleteEndpoint' => null,
            'filetypeIconAlias' => null,
            'thumbnailWidth' => null,
            'thumbnailHeight' => null,
        ],
        'cache' => [
            'path' => null,
            'url' => null,
        ],
    ],
];
