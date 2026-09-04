# API — CacheFile

Fluent file/image helper with filesystem caching and deterministic filename generation. Wraps `FileProvider` to produce cached URLs for templates and inline SVG content.

## Constructor

```php
use Blackcube\FileProvider\CacheFile;

$cacheFile = new CacheFile(
    fileProvider: $provider,
    aliases: $aliases,
    cachePath: '@assets',      // alias resolved to local cache directory
    cacheUrl: '@assetsUrl',    // alias resolved to public URL base
);
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `$fileProvider` | `FileProvider` | — | FileProvider instance (required) |
| `$aliases` | `Aliases` | — | Yii3 aliases instance (required) |
| `$cachePath` | `?string` | `null` | Cache filesystem path alias |
| `$cacheUrl` | `?string` | `null` | Cache URL alias |

Throws `RuntimeException` if `cachePath` or `cacheUrl` is not configured when generating output.

## from

Static factory. Returns a new `CacheFile` configured for the given path. Uses `Injector::get()` to resolve the singleton.

```php
$file = CacheFile::from('@blfs/images/photo.jpg');
```

## Generating URLs (__toString)

Cast to string to get the cached URL. The cache filename is deterministic: `{filename}-{hash}.{ext}` where hash = `substr(sha1(relativePath + opsHash), 0, 6)`.

```php
// Simple URL (no processing)
$url = (string) CacheFile::from('@blfs/images/photo.jpg');

// With image processing
$url = (string) CacheFile::from('@blfs/images/photo.jpg')->scale(1024);
```

Returns empty string if the path is empty or the file does not exist.

```php
$url = (string) CacheFile::from('@blfs/missing.jpg');
// ''
```

## Image processing (fluent)

Same pipeline as `FileProvider`. Each call returns a new immutable `CacheFile` instance. The processing is applied when the cached file is generated.

### scale

Scale proportionally. At least one dimension required.

```php
$url = (string) CacheFile::from('@blfs/photo.jpg')->scale(1024);

$url = (string) CacheFile::from('@blfs/photo.jpg')->scale(null, 600);
```

### cover

Fill area and crop overflow.

```php
$url = (string) CacheFile::from('@blfs/photo.jpg')->cover(800, 600);
```

### pad

Contain in area with background color.

```php
$url = (string) CacheFile::from('@blfs/photo.jpg')->pad(1920, 1080, '#ffffff');
```

### crop

Crop to dimensions at a specific position. Without a position, the image is scaled to
fill the area first, exactly like `cover()` — it is not a cut out of the original.

```php
$url = (string) CacheFile::from('@blfs/photo.jpg')->crop(200, 200);

$url = (string) CacheFile::from('@blfs/photo.jpg')->crop(200, 200, 50, 100);
```

### rotate

Rotate counterclockwise by angle in degrees.

```php
$url = (string) CacheFile::from('@blfs/photo.jpg')->rotate(90);
```

### flip

Mirror horizontally or vertically.

```php
$url = (string) CacheFile::from('@blfs/photo.jpg')->flip('horizontal');
```

### grayscale

Convert to grayscale.

```php
$url = (string) CacheFile::from('@blfs/photo.jpg')->grayscale();
```

### blur

Apply gaussian blur.

```php
$url = (string) CacheFile::from('@blfs/photo.jpg')->blur(10);
```

### watermark

Add watermark image.

```php
$url = (string) CacheFile::from('@blfs/photo.jpg')
    ->watermark('/path/to/logo.png', 'bottom-right', 10);
```

### quality

Set output quality (0-100).

```php
$url = (string) CacheFile::from('@blfs/photo.jpg')->quality(75);
```

### format

Convert format (jpg, png, webp, etc.).

```php
$url = (string) CacheFile::from('@blfs/photo.jpg')->format('webp');
```

### webp / png / jpg

Shortcuts for `format()`.

```php
$url = (string) CacheFile::from('@blfs/photo.jpg')->webp();
$url = (string) CacheFile::from('@blfs/photo.jpg')->png();
$url = (string) CacheFile::from('@blfs/photo.jpg')->jpg();
```

### Chaining

```php
$url = (string) CacheFile::from('@blfs/photo.jpg')
    ->cover(800, 600)
    ->grayscale()
    ->quality(85)
    ->webp();
```

## thumbnail

Generate a thumbnail preview. Behavior depends on file type:

- **Image** (png, jpg, jpeg, gif, webp, avif): resized using `cover()` to the given dimensions
- **SVG**: cached as-is (scales via CSS)
- **Other**: returns the filetype icon from `resources/filetypes/<ext>.png`

Passing a single dimension makes the thumbnail square.

```php
$url = (string) CacheFile::from('@blfs/photo.jpg')->thumbnail(200, 200);

$url = (string) CacheFile::from('@blfs/photo.jpg')->thumbnail(200);

$url = (string) CacheFile::from('@blfs/icon.svg')->thumbnail(200, 200);

$url = (string) CacheFile::from('@blfs/document.pdf')->thumbnail(200, 200);
```

## SVG methods

### svg

Return SVG content. By default returns inline SVG content. With `$inline = false`, returns the cached URL instead.

The `$options` array injects HTML attributes on the root `<svg>` tag using DOMDocument. Options override existing attributes; other attributes and child elements are preserved.

```php
// Inline SVG (default)
$svgContent = CacheFile::from('@blfs/icons/check.svg')->svg();

// Inline SVG with CSS classes
$svgContent = CacheFile::from('@blfs/icons/check.svg')->svg([
    'class' => 'w-5 h-5 text-primary-700',
]);

// Inline SVG with multiple attributes
$svgContent = CacheFile::from('@blfs/icons/check.svg')->svg([
    'class' => 'icon',
    'aria-hidden' => 'true',
    'width' => '24px',
    'height' => '24px',
]);

// Cached URL (no inline)
$url = CacheFile::from('@blfs/icons/check.svg')->svg(inline: false);
```

Returns empty string if the path is empty or the file does not exist.

### svgFile

Shortcut for `svg(inline: false)`. Returns the cached SVG URL.

```php
$url = CacheFile::from('@blfs/icons/check.svg')->svgFile();
```

## Cache behavior

- Cache files are written to the `cachePath` directory, preserving the relative path structure from the filesystem
- Image files: filename includes a 6-character SHA1 hash of `relativePath + opsHash` to differentiate processing variants
- SVG files (via `svg()`/`svgFile()`): filename is the original relative path (no hash, no transformation)
- Cache is checked before processing: if the cached file already exists on disk, it is served directly
- Cache is not invalidated automatically — delete cached files manually or via deployment
