# Blackcube FileProvider

Multi-filesystem file provider with prefix routing, image processing, cached file helpers, and Resumable.js upload.

You write `@blfs/image.jpg`, FileProvider routes to S3. You chain `->cover(300, 200)->read()`, it processes on the fly. You never touch Flysystem directly.

[![License](https://img.shields.io/badge/license-BSD--3--Clause-blue.svg)](LICENSE.md)
[![Packagist Version](https://img.shields.io/packagist/v/blackcube/fileprovider.svg)](https://packagist.org/packages/blackcube/fileprovider)
[![Warning](https://img.shields.io/badge/Blackcube-Warning-orange)](BLACKCUBE_WARNING.md)

## Quickstart

```bash
composer require blackcube/fileprovider
```

```php
use Blackcube\FileProvider\FileProvider;
use Blackcube\FileProvider\Flysystem\FlysystemLocal;
use Yiisoft\Aliases\Aliases;

$provider = new FileProvider(new Aliases());
$provider->addFilesystem('@bltmp', new FlysystemLocal('/tmp/uploads'));
$provider->addFilesystem('@blfs', new FlysystemLocal('/var/www/storage'));

// Write, move, read
$provider->write('@bltmp/upload.jpg', $content);
$provider->move('@bltmp/upload.jpg', '@blfs/images/photo.jpg');
$content = $provider->read('@blfs/images/photo.jpg');

// Image processing (requires intervention/image)
$thumbnail = $provider->cover(200, 200)->read('@blfs/images/photo.jpg');
```

## Tests

```bash
# Unit tests (Provider, Integration, Local suites)
make test-unit

# Functional tests (starts HTTP server)
make test-functional

# All tests
make test
```

## Documentation

- [Overview & prerequisites](docs/index.md)
- [Installation (standalone)](docs/installation-standalone.md)
- [Installation (Yii)](docs/installation-yii.md)
- [API — FileProvider](docs/api-fileprovider.md)
- [API — CacheFile](docs/api-cachefile.md)
- [API — Resumable](docs/api-resumable.md)

## License

BSD-3-Clause. See [LICENSE.md](LICENSE.md).

## Author

Philippe Gaultier <philippe@blackcube.io>
