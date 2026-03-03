<?php

declare(strict_types=1);

/**
 * params.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2026 Blackcube
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
        'imageDriver' => null,  // null = auto-detect, 'gd', 'imagick', 'vips'

        // Resumable upload configuration
        'resumable' => [
            'tmpPrefix' => FileProvider::ALIAS_TMP,
            'chunkSize' => 524288,  // 512 KB
            'uploadEndpoint' => '@web/fileprovider/upload',
            'previewEndpoint' => '@web/fileprovider/preview',
            'deleteEndpoint' => '@web/fileprovider/delete',
            'filetypeIconAlias' => '@fileprovider/filetypes/',
            'thumbnailWidth' => 200,
            'thumbnailHeight' => 200,
        ],
    ],
];
