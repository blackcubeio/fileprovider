# FileProvider

Multi-filesystem file provider with prefix routing, image processing, cached file helpers, and Resumable.js upload.

Path prefixes (`@bltmp`, `@blfs`, `@blcdn`...) route operations to the right filesystem transparently. Cross-filesystem moves, fluent image processing, SVG inline rendering, and chunked uploads work out of the box.

## Why FileProvider?

| Approach | Problem |
|----------|---------|
| Raw Flysystem | One adapter = one filesystem. Need S3 + local? Manage two adapters. |
| Multiple adapters | Which one handles `/tmp/upload.jpg`? Which one handles `/cdn/image.jpg`? |
| Manual routing | `if (str_starts_with($path, '@tmp'))` everywhere |
| Image processing | Read, process, write back. Three operations for one image. |
| **FileProvider** | None of the above |

**You use path prefixes.** `@bltmp/file.jpg` = temporary storage. `@blfs/file.jpg` = permanent storage. FileProvider routes automatically.

**Cross-filesystem operations are transparent.** `->move('@bltmp/upload.jpg', '@blfs/final.jpg')` — local to S3, S3 to local, whatever. One method.

**Image processing is fluent.** `->scale(300)->grayscale()->read('@blfs/image.jpg')`. Chain what you need.

**CacheFile generates cached URLs.** `CacheFile::from('@blfs/photo.jpg')->scale(1024)` — deterministic filename, on-disk cache, one-liner in templates.

**Resumable uploads work out of the box.** Chunks, resume, preview, delete. Three handlers, zero boilerplate.

## Prerequisites

- PHP 8.4+
- `league/flysystem ^3.34`
- `blackcube/injector ^1.0`
- `yiisoft/aliases ^3.1`

### Optional dependencies

```bash
# S3 / MinIO support
composer require league/flysystem-aws-s3-v3

# Image processing (scale, cover, pad, crop, watermark...)
composer require intervention/image

# FTP support
composer require league/flysystem-ftp

# SFTP support
composer require league/flysystem-sftp-v3
```

## Caveats

**Image processing is not free.** Each `->scale()` or `->grayscale()` reads the full image into memory, processes it, and outputs. For a 20MB photo, that's 20MB+ in memory per request. Thumbnails on upload? Fine. On-the-fly processing for every request? Use a CDN with edge processing.

**Cross-filesystem moves are not atomic.** `->move('@bltmp/file.jpg', '@blfs/file.jpg')` = read + write + delete. If write fails, the source remains. If delete fails after write, you have duplicates. For critical data, verify destination exists before deleting source.

**Resumable.js is for uploads, not downloads.** The chunked protocol handles uploads. For large downloads, use signed URLs or streaming.

## Table of contents

- [Installation (standalone)](installation-standalone.md)
- [Installation (Yii)](installation-yii.md)
- [API — FileProvider](api-fileprovider.md)
- [API — CacheFile](api-cachefile.md)
- [API — Resumable](api-resumable.md)
