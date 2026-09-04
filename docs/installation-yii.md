# Installation (Yii)

```bash
composer require blackcube/fileprovider
```

The package ships with config-plugin support. Configuration is done via DI and params.

## Parameters

Configure `config/common/params.php`:

```php
<?php

declare(strict_types=1);

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
        'imageDriver' => null,  // null = auto-detect, 'gd', 'imagick', 'vips'
        'resumable' => [
            'chunkSize' => 524288,
            'uploadEndpoint' => '/fileprovider/upload',
            'previewEndpoint' => '/fileprovider/preview',
            'deleteEndpoint' => '/fileprovider/delete',
        ],
        'cache' => [
            'path' => '@assets',
            'url' => '@assetsUrl',
        ],
    ],
];
```

### Parameter reference

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `filesystems` | `array` | `[]` | Map of alias => filesystem config |
| `filesystems.*.type` | `string` | — | `local`, `s3`, `ftp`, `sftp` |
| `filesystems.*.path` | `string` | — | Root path (local) or bucket (s3) |
| `defaultAlias` | `string` | `@blfs` | Default filesystem for non-prefixed paths |
| `imageDriver` | `?string` | `null` | Force image driver (`gd`, `imagick`, `vips`) or `null` for auto-detect |
| `tempAlias` | `string` | `@bltmp` | Temporary uploads alias |
| `resumable.chunkSize` | `int` | `524288` | Chunk size in bytes (default 512KB) |
| `resumable.uploadEndpoint` | `?string` | `null` | Upload URL |
| `resumable.previewEndpoint` | `?string` | `null` | Preview URL |
| `resumable.deleteEndpoint` | `?string` | `null` | Delete URL |
| `cache.path` | `?string` | `null` | Cache filesystem path alias (e.g. `@assets`) |
| `cache.url` | `?string` | `null` | Cache URL alias (e.g. `@assetsUrl`) |

## DI definitions

The DI definitions are provided by the package in `config/common/di.php`:

```php
<?php

declare(strict_types=1);

use Blackcube\FileProvider\CacheFile;
use Blackcube\FileProvider\FileProvider;
use Blackcube\FileProvider\Interfaces\FileProviderInterface;
use Blackcube\FileProvider\Resumable\ResumableConfig;

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
```

## Route registration

Register the three Resumable handlers in your route configuration:

```php
use Blackcube\FileProvider\Handlers\ResumableUploadHandler;
use Blackcube\FileProvider\Handlers\ResumablePreviewHandler;
use Blackcube\FileProvider\Handlers\ResumableDeleteHandler;

Route::methods(['GET', 'POST'], '/fileprovider/upload')
    ->action(ResumableUploadHandler::class)
    ->name('fileprovider.upload'),
Route::methods(['GET'], '/fileprovider/preview')
    ->action(ResumablePreviewHandler::class)
    ->name('fileprovider.preview'),
Route::methods(['DELETE'], '/fileprovider/delete')
    ->action(ResumableDeleteHandler::class)
    ->name('fileprovider.delete'),
```
