# Installation (standalone)

```bash
composer require blackcube/fileprovider
```

## Configure FileProvider

```php
use Blackcube\FileProvider\FileProvider;
use Blackcube\FileProvider\Flysystem\FlysystemLocal;
use Blackcube\FileProvider\Flysystem\FlysystemAwsS3;
use Yiisoft\Aliases\Aliases;

$aliases = new Aliases([
    '@runtime' => __DIR__ . '/runtime',
]);

$provider = new FileProvider($aliases);
$provider->addFilesystem('@bltmp', new FlysystemLocal('/path/to/tmp'));
$provider->addFilesystem('@blfs', new FlysystemAwsS3(
    bucket: 'my-bucket',
    key: 'ACCESS_KEY',
    secret: 'SECRET_KEY',
    region: 'eu-west-1',
));
```

## Use it

```php
// Write to temporary storage
$provider->write('@bltmp/upload.jpg', $content);

// Move to permanent storage (cross-filesystem)
$provider->move('@bltmp/upload.jpg', '@blfs/images/photo.jpg');

// Read with image processing
$thumbnail = $provider->cover(200, 200)->read('@blfs/images/photo.jpg');
```

## Configure CacheFile

CacheFile requires a cache directory (writable) and its public URL:

```php
use Blackcube\FileProvider\CacheFile;

$cacheFile = new CacheFile(
    fileProvider: $provider,
    aliases: $aliases,
    cachePath: '/var/www/public/assets',
    cacheUrl: '/assets',
);
```

## Configure Resumable upload

```php
use Blackcube\FileProvider\Resumable\ResumableConfig;
use Blackcube\FileProvider\Resumable\ResumableService;

$config = new ResumableConfig(
    uploadEndpoint: '/fileprovider/upload',
    previewEndpoint: '/fileprovider/preview',
    deleteEndpoint: '/fileprovider/delete',
);

$service = new ResumableService($provider, $config);
```
