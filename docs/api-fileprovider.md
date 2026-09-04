# API — FileProvider

## Interfaces

### FileProviderInterface

Minimal contract for file providers.

```php
interface FileProviderInterface
{
    public function canHandle(string $path): bool;
    public function isTempPath(string $path): bool;
    public function getTempAlias(): string;
}
```

### FlysystemProviderInterface

Full interface for filesystem adapters. All adapters implement this.

| Method | Description |
|--------|-------------|
| `fileExists(string $path): bool` | Check if file exists |
| `directoryExists(string $path): bool` | Check if directory exists |
| `has(string $path): bool` | Check if file or directory exists |
| `read(string $path): string` | Read file contents |
| `readStream(string $path): mixed` | Read file as stream (resource) |
| `write(string $path, string $contents, array $config = []): void` | Write file contents |
| `writeStream(string $path, mixed $stream, array $config = []): void` | Write file from stream |
| `delete(string $path): void` | Delete a file |
| `deleteDirectory(string $path): void` | Delete a directory recursively |
| `createDirectory(string $path, array $config = []): void` | Create a directory |
| `move(string $source, string $destination, array $config = []): void` | Move a file |
| `copy(string $source, string $destination, array $config = []): void` | Copy a file |
| `mimeType(string $path): string` | Get MIME type |
| `fileSize(string $path): int` | Get file size |
| `lastModified(string $path): int` | Get last modified timestamp |
| `visibility(string $path): string` | Get file visibility |
| `setVisibility(string $path, string $visibility): void` | Set file visibility |
| `listContents(string $path, bool $recursive = false): DirectoryListing` | List directory contents |

## FileProvider

Multi-filesystem manager. Routes operations based on path prefix aliases. Implements both `FileProviderInterface` and `FlysystemProviderInterface`.

### Constants

| Constant | Value | Description |
|----------|-------|-------------|
| `ALIAS_TMP` | `@bltmp` | Default temporary storage prefix |
| `ALIAS_FS` | `@blfs` | Default permanent storage prefix |

### Constructor

```php
$provider = new FileProvider(
    aliases: new Aliases(),
    filesystems: [],         // alias => config array (for Yii config-plugin)
    defaultAlias: '@blfs',   // fallback for paths without prefix
    tempAlias: '@bltmp',     // temporary uploads alias
    imageDriver: null,       // null = auto-detect, 'gd', 'imagick', 'vips'
);
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `$aliases` | `Aliases` | — | Yii3 aliases instance (required) |
| `$filesystems` | `array` | `[]` | Filesystem config array (alias => config) |
| `$defaultAlias` | `string` | `@blfs` | Default filesystem alias |
| `$tempAlias` | `string` | `@bltmp` | Temporary uploads alias |
| `$imageDriver` | `?string` | `null` | Force image driver or `null` for auto-detect |

### Filesystem management

#### addFilesystem

Register a filesystem under an alias prefix.

```php
use Blackcube\FileProvider\Flysystem\FlysystemLocal;

$provider->addFilesystem('@bltmp', new FlysystemLocal('/tmp/uploads'));
$provider->addFilesystem('@blfs', new FlysystemLocal('/var/www/storage'));
```

#### getFilesystem

Retrieve a registered filesystem. Throws `UnknownFilesystemException` if not found.

```php
$fs = $provider->getFilesystem('@blfs');
```

#### hasFilesystem

Check if a filesystem is registered under an alias.

```php
if ($provider->hasFilesystem('@blfs')) {
    // filesystem is available
}
```

#### getAliases

List all registered filesystem aliases.

```php
$aliases = $provider->getAliases();      // ['@bltmp', '@blfs']
$aliases = $provider->getAliases(withTmp: false); // ['@blfs']
```

### Path resolution

#### resolvePath

Split a prefixed path into alias + relative path.

```php
[$alias, $relativePath] = $provider->resolvePath('@blfs/images/photo.jpg');
// $alias = '@blfs'
// $relativePath = 'images/photo.jpg'

[$alias, $relativePath] = $provider->resolvePath('no-prefix.txt');
// $alias = '@blfs' (default alias)
// $relativePath = 'no-prefix.txt'
```

#### canHandle

Check if a path prefix matches a registered filesystem.

```php
$provider->canHandle('@blfs/file.jpg');   // true
$provider->canHandle('@unknown/file.jpg'); // false
```

### File operations

#### write / read

```php
$provider->write('@bltmp/upload.jpg', $content);

$content = $provider->read('@blfs/images/photo.jpg');
```

#### writeStream / readStream

```php
$stream = fopen('/tmp/large-file.zip', 'r');
$provider->writeStream('@blfs/archive.zip', $stream);
fclose($stream);

$stream = $provider->readStream('@blfs/archive.zip');
$content = stream_get_contents($stream);
fclose($stream);
```

#### move

Move a file, including across filesystems. Cross-filesystem moves are read + write + delete (not atomic).

```php
// Same filesystem
$provider->move('@blfs/old-name.jpg', '@blfs/new-name.jpg');

// Cross-filesystem (e.g. local → S3)
$provider->move('@bltmp/upload.jpg', '@blfs/images/photo.jpg');
```

#### copy

```php
$provider->copy('@blfs/original.jpg', '@bltmp/backup.jpg');
```

#### delete / deleteDirectory / createDirectory

```php
$provider->delete('@blfs/old-file.jpg');

$provider->deleteDirectory('@bltmp/chunks');

$provider->createDirectory('@blfs/images');
```

#### fileExists / directoryExists / has

```php
if ($provider->fileExists('@blfs/image.jpg')) {
    // file exists
}

if ($provider->directoryExists('@blfs/images')) {
    // directory exists
}

// has() checks both files and directories
if ($provider->has('@blfs/something')) {
    // file or directory exists
}
```

#### mimeType / fileSize / lastModified

```php
$mime = $provider->mimeType('@blfs/photo.jpg');
// 'image/jpeg'

$size = $provider->fileSize('@blfs/photo.jpg');
// 245760

$timestamp = $provider->lastModified('@blfs/photo.jpg');
// 1709500000
```

#### visibility / setVisibility

```php
$visibility = $provider->visibility('@blfs/photo.jpg');
// 'public' or 'private'

$provider->setVisibility('@blfs/photo.jpg', 'private');
```

Note: `setVisibility` is not supported on S3/MinIO (no ACL support).

#### listContents

```php
$contents = $provider->listContents('@blfs', recursive: false);
foreach ($contents as $item) {
    echo $item->path();    // relative path
    echo $item->isFile();  // true/false
    echo $item->isDir();   // true/false
}
```

### Image processing (fluent)

Requires `intervention/image`. Chain processors before `read()` or `write()`. Each call returns a new immutable instance.

#### scale

Scale proportionally. At least one dimension required.

```php
$scaled = $provider->scale(300)->read('@blfs/image.jpg');

$scaled = $provider->scale(null, 200)->read('@blfs/image.jpg');
```

#### cover

Fill area and crop overflow.

```php
$covered = $provider->cover(800, 600)->read('@blfs/image.jpg');
```

#### pad

Contain in area with background color.

```php
$padded = $provider->pad(1920, 1080, '#ffffff')->read('@blfs/image.jpg');
```

#### crop

Crop to dimensions at a specific position. Without a position, the image is scaled to
fill the area first, exactly like `cover()` — it is not a cut out of the original.

```php
$cropped = $provider->crop(200, 200)->read('@blfs/image.jpg');

$cropped = $provider->crop(200, 200, 50, 100)->read('@blfs/image.jpg');
```

#### rotate

Rotate counterclockwise by angle in degrees.

```php
$rotated = $provider->rotate(90)->read('@blfs/image.jpg');
```

#### flip

Mirror horizontally or vertically.

```php
$flipped = $provider->flip('horizontal')->read('@blfs/image.jpg');

$flipped = $provider->flip('vertical')->read('@blfs/image.jpg');
```

#### grayscale

Convert to grayscale.

```php
$grey = $provider->grayscale()->read('@blfs/image.jpg');
```

#### blur

Apply gaussian blur (0-100).

```php
$blurred = $provider->blur(10)->read('@blfs/image.jpg');
```

#### watermark

Add watermark image at a given position with padding.

```php
$watermarked = $provider
    ->watermark('/path/to/logo.png', 'bottom-right', 10)
    ->read('@blfs/image.jpg');
```

#### quality

Set output quality (0-100).

```php
$compressed = $provider->quality(75)->read('@blfs/image.jpg');
```

#### format

Convert format (jpg, png, webp, avif, etc.).

```php
$webp = $provider->format('webp')->read('@blfs/image.jpg');
```

#### Chaining

Processors are immutable and chainable:

```php
$processed = $provider
    ->cover(800, 600)
    ->grayscale()
    ->quality(85)
    ->read('@blfs/image.jpg');

// On write
$provider
    ->pad(1920, 1080, '#ffffff')
    ->watermark('/path/to/logo.png', 'bottom-right', 10)
    ->write('@blfs/image.jpg', $contents);
```

#### Driver auto-detection

Priority: vips > imagick > gd. Override via constructor:

```php
$provider = new FileProvider(
    new Aliases(),
    imageDriver: 'gd',
);
```

## Adapters

All adapters extend the abstract `Flysystem` class and implement `FlysystemProviderInterface`.

| Adapter | Description | Requires |
|---------|-------------|----------|
| `FlysystemLocal` | Local filesystem | — |
| `FlysystemAwsS3` | AWS S3 / MinIO | `league/flysystem-aws-s3-v3` |
| `FlysystemFtp` | FTP | `league/flysystem-ftp` |
| `FlysystemSftp` | SFTP | `league/flysystem-sftp-v3` |

### FlysystemLocal

```php
use Blackcube\FileProvider\Flysystem\FlysystemLocal;

$local = new FlysystemLocal('/var/www/storage');
```

### FlysystemAwsS3

```php
use Blackcube\FileProvider\Flysystem\FlysystemAwsS3;

$s3 = new FlysystemAwsS3(
    bucket: 'my-bucket',
    key: 'ACCESS_KEY',
    secret: 'SECRET_KEY',
    region: 'eu-west-1',
);

// MinIO (S3-compatible)
$minio = new FlysystemAwsS3(
    bucket: 'my-bucket',
    key: 'minioadmin',
    secret: 'minioadmin',
    region: 'us-east-1',
    endpoint: 'http://localhost:9000',
    pathStyleEndpoint: true,  // Required for MinIO
);
```

### FlysystemFtp

```php
use Blackcube\FileProvider\Flysystem\FlysystemFtp;

$ftp = new FlysystemFtp(
    host: 'ftp.example.com',
    root: '/public_html',
    username: 'user',
    password: 'pass',
);
```

### FlysystemSftp

```php
use Blackcube\FileProvider\Flysystem\FlysystemSftp;

$sftp = new FlysystemSftp(
    host: 'sftp.example.com',
    username: 'user',
    root: '/home/user/files',
    privateKey: '/path/to/key',
);
```

## ImageManagerFactory

Factory for creating Intervention Image `ImageManager`. Auto-detects driver (vips > imagick > gd), supports forced driver selection.

```php
use Blackcube\FileProvider\Image\ImageManagerFactory;

$manager = ImageManagerFactory::create();        // auto-detect
$manager = ImageManagerFactory::create('gd');     // force GD
$manager = ImageManagerFactory::create('imagick'); // force Imagick
```

Returns `null` if no driver is available. Throws `InvalidArgumentException` for unknown driver names.

## Exceptions

| Exception | Description |
|-----------|-------------|
| `UnknownFilesystemException` | Filesystem alias not found (extends `InvalidArgumentException`) |
| `InvalidConfigurationException` | Invalid configuration (extends `InvalidArgumentException`) |
