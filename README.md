# Blackcube FileProvider

> **⚠️ Blackcube Warning**
>
> This is not a Flysystem wrapper. It's a multi-filesystem router with image processing.
>
> You write `@blfs/image.jpg`, FileProvider routes to S3. You chain `->resize(300, 200)->read()`, it processes on the fly.
> You never touch Flysystem directly. You never juggle adapters.

Multi-filesystem file provider with prefix routing, image processing and Resumable.js upload.

[![License](https://img.shields.io/badge/license-BSD--3--Clause-blue.svg)](LICENSE.md)
[![Packagist Version](https://img.shields.io/packagist/v/blackcube/fileprovider.svg)](https://packagist.org/packages/blackcube/fileprovider)
[![Warning](https://img.shields.io/badge/Blackcube-Warning-orange)](BLACKCUBE_WARNING.md)

## Installation

```bash
composer require blackcube/fileprovider
```

Optional dependencies:

```bash
# S3/MinIO support
composer require league/flysystem-aws-s3-v3

# Image processing
composer require intervention/image

# FTP/SFTP support
composer require league/flysystem-ftp
composer require league/flysystem-sftp-v3
```

## Why FileProvider?

| Approach | Problem |
|----------|---------|
| Raw Flysystem | One adapter = one filesystem. Need S3 + local? Manage two adapters. |
| Multiple adapters | Which one handles `/tmp/upload.jpg`? Which one handles `/cdn/image.jpg`? |
| Manual routing | `if (str_starts_with($path, '@tmp'))` everywhere |
| Image processing | Read, process, write back. Three operations for one resize. |
| **FileProvider** | None of the above |

**You use path prefixes.** `@bltmp/file.jpg` → temporary storage. `@blfs/file.jpg` → permanent storage. FileProvider routes automatically.

**Cross-filesystem operations are transparent.** `->move('@bltmp/upload.jpg', '@blfs/final.jpg')` — local to S3, S3 to local, whatever. One method.

**Image processing is fluent.** `->resize(300, 200)->greyscale()->read('@blfs/image.jpg')`. Chain what you need.

**Resumable uploads work out of the box.** Chunks, resume, preview, delete. Three actions, zero boilerplate.

## How It Works

### Prefix Routing

| Column | Purpose |
|--------|---------|
| `@bltmp` | Temporary storage (uploads, chunks) |
| `@blfs` | Permanent storage (final files) |
| `@blcdn` | CDN storage (public assets) |

You define the prefixes. You assign a filesystem to each. FileProvider does the rest.

```php
$provider = new FileProvider([
    '@bltmp' => new FlysystemLocal('/tmp/uploads'),
    '@blfs' => new FlysystemAwsS3(bucket: 'my-bucket', ...),
]);

// Routes to local
$provider->write('@bltmp/upload.jpg', $content);

// Routes to S3
$provider->move('@bltmp/upload.jpg', '@blfs/images/photo.jpg');
```

### Image Processing

Fluent API. Chain processors. Read or write.

```php
// Resize on read
$resized = $provider
    ->resize(300, 200)
    ->read('@blfs/image.jpg');

// Chain processors
$processed = $provider
    ->resize(800, null)
    ->greyscale()
    ->quality(85)
    ->read('@blfs/image.jpg');

// Process on write
$provider
    ->resize(1920, 1080)
    ->watermark('/path/to/logo.png', 'bottom-right', 10)
    ->write('@blfs/image.jpg', $contents);
```

Driver auto-detection: vips > imagick > gd. Override if needed.

## Quick Start

### 1. Configure FileProvider

```php
use Blackcube\FileProvider\FileProvider;
use Blackcube\FileProvider\FlysystemLocal;
use Blackcube\FileProvider\FlysystemAwsS3;

$provider = new FileProvider([
    '@bltmp' => new FlysystemLocal('/path/to/tmp'),
    '@blfs' => new FlysystemAwsS3(
        bucket: 'my-bucket',
        key: 'ACCESS_KEY',
        secret: 'SECRET_KEY',
        region: 'eu-west-1',
    ),
]);
```

### 2. Use it

```php
// Write
$provider->write('@bltmp/upload.jpg', $content);

// Read
$content = $provider->read('@blfs/images/photo.jpg');

// Move across filesystems
$provider->move('@bltmp/upload.jpg', '@blfs/images/photo.jpg');

// Copy
$provider->copy('@blfs/original.jpg', '@bltmp/backup.jpg');

// Delete
$provider->delete('@blfs/old-file.jpg');

// Check existence
if ($provider->fileExists('@blfs/image.jpg')) {
    // ...
}

// Check if path is handled
if ($provider->canHandle('@blfs/file.jpg')) {
    // ...
}
```

## Available Adapters

| Adapter | Description | Requires |
|---------|-------------|----------|
| `FlysystemLocal` | Local filesystem | - |
| `FlysystemAwsS3` | AWS S3 / MinIO | `league/flysystem-aws-s3-v3` |
| `FlysystemFtp` | FTP | `league/flysystem-ftp` |
| `FlysystemSftp` | SFTP | `league/flysystem-sftp-v3` |

### S3-Compatible Storage (MinIO)

```php
$s3 = new FlysystemAwsS3(
    bucket: 'my-bucket',
    key: 'minioadmin',
    secret: 'minioadmin',
    region: 'us-east-1',
    endpoint: 'http://localhost:9000',
    pathStyleEndpoint: true, // Required for MinIO
);
```

## Image Processing

Requires `intervention/image`.

| Method | Description |
|--------|-------------|
| `resize(?int $width, ?int $height)` | Scale proportionally |
| `crop(int $width, int $height, ?int $x, ?int $y)` | Crop to dimensions |
| `rotate(float $angle)` | Rotate counterclockwise |
| `flip(string $direction)` | Mirror (`horizontal` or `vertical`) |
| `greyscale()` | Convert to greyscale |
| `blur(int $amount)` | Apply gaussian blur (0-100) |
| `watermark(string $image, string $position, int $padding)` | Add watermark |
| `quality(int $quality)` | Set output quality (0-100) |
| `format(string $format)` | Convert format (jpg, png, webp, etc.) |

### Force Driver

```php
$provider = new FileProvider(
    filesystems: ['@blfs' => $fs],
    defaultAlias: '@blfs',
    imageDriver: 'gd', // Default: auto-detection vips > imagick > gd
);
```

## Resumable.js Upload

Chunked upload with resume support. Three actions, ready to use.

### Configuration (Yii3 example)

```php
// config/params.php
'blackcube/fileprovider' => [
    'resumable' => [
        'tmpPrefix' => '@bltmp',
        'chunkSize' => 524288,  // 512 KB
        'uploadEndpoint' => '/fileprovider/upload',
        'previewEndpoint' => '/fileprovider/preview',
        'deleteEndpoint' => '/fileprovider/delete',
        'filetypeIconAlias' => '@fileprovider/filetypes/',
        'thumbnailWidth' => 200,
        'thumbnailHeight' => 200,
    ],
],
```

### Actions

| Action | Method | Description |
|--------|--------|-------------|
| `ResumableUploadAction` | GET | Test if chunk exists (resume) |
| `ResumableUploadAction` | POST | Upload chunk |
| `ResumablePreviewAction` | GET | Preview / thumbnail / icon |
| `ResumableDeleteAction` | DELETE | Delete file (@bltmp only) |

### Upload Flow

```
Browser (Resumable.js)
    │
    ├─► GET /upload?resumable*     → 200 (exists) / 204 (no)
    │
    ▼ POST /upload (multipart + chunk)
ResumableUploadAction
    │
    ▼ saveChunk()
ResumableService ──► @bltmp/{identifier}/{filename}.part{n}
    │
    ▼ isComplete() → assemble()
@bltmp/{filename}
    │
    ▼ Form submit (business logic)
FileProvider->move('@bltmp/...', '@blfs/...')
    │
    ▼
@blfs/, @blcdn/, etc.
```

### Security

| Protection | Mechanism |
|------------|-----------|
| Path traversal filename | `cleanFilename()` removes `../`, `..\\`, `..` |
| Path traversal delete | `deleteTmpFile()` only allows `@bltmp/` |
| Flysystem detection | `PathTraversalDetected` → 403 Forbidden |

## DI Configuration (Yii3 example)

```php
// config/common/di.php
use Blackcube\FileProvider\FileProvider;
use Blackcube\FileProvider\Contracts\FileProviderInterface;
use Blackcube\FileProvider\FlysystemLocal;
use Blackcube\FileProvider\FlysystemAwsS3;
use Yiisoft\Aliases\Aliases;

return [
    FileProviderInterface::class => static function (Aliases $aliases): FileProviderInterface {
        return new FileProvider([
            '@bltmp' => new FlysystemLocal($aliases->get('@runtime/tmp')),
            '@blfs' => new FlysystemAwsS3(
                bucket: $_ENV['S3_BUCKET'],
                key: $_ENV['S3_KEY'],
                secret: $_ENV['S3_SECRET'],
                region: $_ENV['S3_REGION'],
            ),
        ]);
    },
];
```

## Let's be honest

**Image processing is not free**

Each `->resize()` or `->greyscale()` reads the full image into memory, processes it, and outputs. For a 20MB photo, that's 20MB+ in memory per request.

**In practice:** Thumbnails on upload? Fine. On-the-fly processing for every request? Use a CDN with edge processing.

**Cross-filesystem moves are not atomic**

`->move('@bltmp/file.jpg', '@blfs/file.jpg')` = read + write + delete. If write fails, the source remains. If delete fails after write, you have duplicates.

**In practice:** For critical data, verify destination exists before deleting source.

**Resumable.js is for uploads, not downloads**

The chunked protocol handles uploads. For large downloads, use signed URLs or streaming.

## Tests

```bash
# All tests (unit + functional)
make test

# Unit tests only
make test-unit

# Functional tests only (starts HTTP server)
make test-functional

# Clean artifacts
make clean
```

## License

BSD-3-Clause. See [LICENSE.md](LICENSE.md).

## Author

Philippe Gaultier <philippe@blackcube.io>
