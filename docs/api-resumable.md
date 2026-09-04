# API — Resumable

Chunked upload with Resumable.js support. Three PSR-15 handlers for upload, preview, and delete.

## ResumableConfig

Centralized configuration for the Resumable.js upload system.

### Constructor

```php
use Blackcube\FileProvider\Resumable\ResumableConfig;

$config = new ResumableConfig(
    chunkSize: 524288,
    uploadEndpoint: '/fileprovider/upload',
    previewEndpoint: '/fileprovider/preview',
    deleteEndpoint: '/fileprovider/delete',
    filetypeIconAlias: null,
    thumbnailWidth: 200,
    thumbnailHeight: 200,
);
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `$chunkSize` | `int` | `524288` | Chunk size in bytes (default 512KB) |
| `$uploadEndpoint` | `?string` | `null` | Upload URL (throws if not set when used) |
| `$previewEndpoint` | `?string` | `null` | Preview URL (throws if not set when used) |
| `$deleteEndpoint` | `?string` | `null` | Delete URL (throws if not set when used) |
| `$filetypeIconAlias` | `?string` | `null` | Path to custom filetype icons |
| `$thumbnailWidth` | `int` | `200` | Preview thumbnail width |
| `$thumbnailHeight` | `int` | `200` | Preview thumbnail height |

### Getters

```php
$config->getChunkSize();       // 524288
$config->getUploadEndpoint();  // '/fileprovider/upload' or throws RuntimeException
$config->getPreviewEndpoint(); // '/fileprovider/preview' or throws RuntimeException
$config->getDeleteEndpoint();  // '/fileprovider/delete' or throws RuntimeException
$config->getFiletypeIconAlias(); // icon path
$config->getThumbnailWidth();  // 200
$config->getThumbnailHeight(); // 200
```

### cleanFilename

Static method. Sanitize a filename by removing path traversal sequences.

```php
$safe = ResumableConfig::cleanFilename('../../../etc/passwd');
// 'etc_passwd'

$safe = ResumableConfig::cleanFilename('my photo (1).jpg');
// 'my_photo_1_.jpg'
```

## ResumableService

Service handling chunk storage, verification, assembly, deletion, and preview generation.

### Constructor

```php
use Blackcube\FileProvider\Resumable\ResumableService;

$service = new ResumableService($provider, $config);
```

### chunkExists

Check if a specific chunk has already been uploaded (for resume support).

```php
$exists = $service->chunkExists('abc123', 'photo.jpg', 3);
```

### saveChunk

Save an uploaded chunk to temporary storage.

```php
$stream = $request->getBody()->detach();
$service->saveChunk('abc123', 'photo.jpg', 3, $stream);
```

### isComplete

Check if all chunks have been uploaded.

```php
$complete = $service->isComplete('abc123', 'photo.jpg', 10);
```

### assemble

Assemble the chunks of that file into the final file. Chunks belonging to another
filename are left alone, even when they share the identifier. Returns the final filename
or empty string on failure.

```php
$filename = $service->assemble('abc123', 'photo.jpg');
// '@bltmp/photo.jpg'
```

### deleteTmpFile

Delete a file from temporary storage. Only allows `@bltmp/` paths. Throws `InvalidArgumentException` for other paths.

```php
$service->deleteTmpFile('@bltmp/photo.jpg');
```

### fileExists

Check whether a file exists on a filesystem this provider handles. Answers `false` for an
unknown alias instead of raising.

```php
$service->fileExists('@bltmp/photo.jpg'); // true
$service->fileExists('@unknown/photo.jpg'); // false
```

### getPreview

Get a preview of a file. Returns a stream, MIME type, and filename, or `null` if the file does not exist.
Opens a stream: to only know whether a file is there, use `fileExists()`.

```php
$preview = $service->getPreview('@bltmp/photo.jpg');
// ['stream' => resource, 'mimeType' => 'image/jpeg', 'filename' => 'photo.jpg']

$preview = $service->getPreview('@bltmp/photo.jpg', original: true);
// full size, no thumbnail
```

### isImage / isSvg

Detect file type by extension.

```php
$service->isImage('@bltmp/photo.jpg'); // true
$service->isImage('@bltmp/doc.pdf');   // false

$service->isSvg('@bltmp/icon.svg');    // true
$service->isSvg('@bltmp/photo.jpg');   // false
```

### getTmpPrefix

```php
$prefix = $service->getTmpPrefix();
// '@bltmp'
```

## Handlers

PSR-15 handlers for HTTP integration.

| Handler | Method | Description |
|---------|--------|-------------|
| `ResumableUploadHandler` | GET | Test if chunk exists (resume) |
| `ResumableUploadHandler` | POST | Upload chunk, assemble when complete |
| `ResumablePreviewHandler` | GET | Preview / thumbnail / filetype icon |
| `ResumableDeleteHandler` | DELETE | Delete file (`@bltmp` only) |

### ResumableUploadHandler

Handles both GET (chunk existence check) and POST (chunk upload).

```php
use Blackcube\FileProvider\Handlers\ResumableUploadHandler;

$handler = new ResumableUploadHandler($responseFactory, $jsonResponseFactory, $resumableService, $resumableConfig);
```

### ResumablePreviewHandler

Returns a preview image for uploaded files. Images are thumbnailed, SVGs are returned as-is, other files get a filetype icon.

```php
use Blackcube\FileProvider\Handlers\ResumablePreviewHandler;

$handler = new ResumablePreviewHandler(
    $responseFactory,
    $streamFactory,
    $resumableService,
    $resumableConfig,
    $aliases,
);
```

### ResumableDeleteHandler

Deletes files from temporary storage only (`@bltmp/`).

```php
use Blackcube\FileProvider\Handlers\ResumableDeleteHandler;

$handler = new ResumableDeleteHandler($responseFactory, $resumableService);
```

## Upload flow

```
Browser (Resumable.js)
    |
    +---> GET /upload?resumable*     -> 200 (exists) / 204 (no)
    |
    v POST /upload (multipart + chunk)
ResumableUploadHandler
    |
    v saveChunk()
ResumableService --> @bltmp/{identifier}/{filename}.part{n}
    |
    v isComplete() -> assemble()
@bltmp/{filename}
    |
    v Form submit (business logic)
FileProvider->move('@bltmp/...', '@blfs/...')
    |
    v
@blfs/, @blcdn/, etc.
```

## Security

| Protection | Mechanism |
|------------|-----------|
| Path traversal (filename) | `cleanFilename()` removes `../`, `..\\`, `..` |
| Path traversal (delete) | `deleteTmpFile()` only allows `@bltmp/` prefix |
| Flysystem detection | `PathTraversalDetected` exception → 403 Forbidden |
