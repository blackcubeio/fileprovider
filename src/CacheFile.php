<?php

declare(strict_types=1);

/**
 * CacheFile.php
 *
 * PHP Version 8.4
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 */

namespace Blackcube\FileProvider;

use Blackcube\FileProvider\Interfaces\FileProviderInterface;
use Blackcube\Injector\Injector;
use Yiisoft\Aliases\Aliases;

/**
 * Fluent file/image helper with filesystem caching.
 *
 * Usage:
 *   <img src="<?php echo CacheFile::from('@blfs/contents/12/photo.jpg')->cover(200, 300); ?>"/>
 *   <img src="<?php echo CacheFile::from('@blfs/doc.pdf')->thumbnail(200, 200); ?>"/>
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 */
class CacheFile
{
    private const HASH_LENGTH = 6;
    private const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif'];
    private const SVG_EXTENSIONS = ['svg'];

    private static ?self $instance = null;

    private ?string $path = null;
    private string $opsHash = '';
    private FileProviderInterface $processedProvider;
    private ?int $thumbnailWidth = null;
    private ?int $thumbnailHeight = null;

    public function __construct(
        private readonly FileProviderInterface $fileProvider,
        private readonly Aliases $aliases,
        private ?string $cachePath = null,
        private ?string $cacheUrl = null,
    ) {
        $this->processedProvider = $fileProvider;
    }

    public static function from(string $path): self
    {
        self::$instance ??= Injector::get(self::class);
        $clone = clone self::$instance;
        $clone->path = $path;
        $clone->opsHash = '';
        $clone->processedProvider = $clone->fileProvider;
        $clone->thumbnailWidth = null;
        $clone->thumbnailHeight = null;
        return $clone;
    }

    public function scale(?int $width, ?int $height = null): self
    {
        $clone = clone $this;
        $clone->processedProvider = $clone->processedProvider->scale($width, $height);
        $clone->opsHash .= '|scale-'.($width ?? 0).'x'.($height ?? 0);
        return $clone;
    }

    public function cover(int $width, int $height): self
    {
        $clone = clone $this;
        $clone->processedProvider = $clone->processedProvider->cover($width, $height);
        $clone->opsHash .= '|cover-'.$width.'x'.$height;
        return $clone;
    }

    public function pad(int $width, int $height, string $background = '#000000'): self
    {
        $clone = clone $this;
        $clone->processedProvider = $clone->processedProvider->pad($width, $height, $background);
        $clone->opsHash .= '|pad-'.$width.'x'.$height.'-'.$background;
        return $clone;
    }

    public function crop(int $width, int $height, ?int $x = null, ?int $y = null): self
    {
        $clone = clone $this;
        $clone->processedProvider = $clone->processedProvider->crop($width, $height, $x, $y);
        $clone->opsHash .= '|crop-'.$width.'x'.$height
            .(($x !== null) ? '-'.$x.','.$y : '');
        return $clone;
    }

    public function rotate(float $angle): self
    {
        $clone = clone $this;
        $clone->processedProvider = $clone->processedProvider->rotate($angle);
        $clone->opsHash .= '|rotate-'.$angle;
        return $clone;
    }

    public function flip(string $direction = 'horizontal'): self
    {
        $clone = clone $this;
        $clone->processedProvider = $clone->processedProvider->flip($direction);
        $clone->opsHash .= '|flip-'.$direction;
        return $clone;
    }

    public function grayscale(): self
    {
        $clone = clone $this;
        $clone->processedProvider = $clone->processedProvider->grayscale();
        $clone->opsHash .= '|grayscale';
        return $clone;
    }

    public function blur(int $amount = 5): self
    {
        $clone = clone $this;
        $clone->processedProvider = $clone->processedProvider->blur($amount);
        $clone->opsHash .= '|blur-'.$amount;
        return $clone;
    }

    public function watermark(string $image, string $position = 'bottom-right', int $padding = 10): self
    {
        $clone = clone $this;
        $clone->processedProvider = $clone->processedProvider->watermark($image, $position, $padding);
        $clone->opsHash .= '|watermark-'.md5($image).'-'.$position.'-'.$padding;
        return $clone;
    }

    public function quality(int $quality): self
    {
        $clone = clone $this;
        $clone->processedProvider = $clone->processedProvider->quality($quality);
        $clone->opsHash .= '|quality-'.$quality;
        return $clone;
    }

    public function format(string $format): self
    {
        $clone = clone $this;
        $clone->processedProvider = $clone->processedProvider->format($format);
        $clone->opsHash .= '|format-'.$format;
        return $clone;
    }

    public function webp(): self
    {
        return $this->format('webp');
    }

    public function png(): self
    {
        return $this->format('png');
    }

    public function jpg(): self
    {
        return $this->format('jpg');
    }

    /**
     * Generates a thumbnail preview.
     * - Image: resized to the given dimensions, a single dimension makes it square
     * - SVG: cached as-is (scales via CSS)
     * - Other: returns the filetype icon from resources/filetypes/<ext>.png
     */
    public function thumbnail(?int $width, ?int $height = null): self
    {
        $clone = clone $this;
        $clone->thumbnailWidth = $width ?? $height;
        $clone->thumbnailHeight = $height ?? $width;
        $clone->opsHash .= '|thumbnail-'.($clone->thumbnailWidth ?? 0).'x'.($clone->thumbnailHeight ?? 0);
        return $clone;
    }

    public function svg(array $options = [], bool $inline = true): string
    {
        if ($this->path === null || $this->path === '') {
            return '';
        }
        if ($this->fileProvider->fileExists($this->path) === false) {
            return '';
        }

        [, $relativePath] = $this->fileProvider->resolvePath($this->path);
        $cached = $this->cacheFile($relativePath, fn (): string => $this->fileProvider->read($this->path));
        if ($cached === null) {
            return '';
        }
        [$cachePath, $cacheUrl] = $cached;

        if ($inline === false) {
            return $cacheUrl;
        }

        $svgContent = file_get_contents($cachePath);
        if ($options !== [] && $svgContent !== '' && $svgContent !== false) {
            $svgContent = $this->applySvgOptions($svgContent, $options);
        }

        return $svgContent !== false ? $svgContent : '';
    }

    public function svgFile(): string
    {
        return $this->svg(inline: false);
    }

    private function applySvgOptions(string $svg, array $options): string
    {
        $dom = new \DOMDocument();
        $previousErrors = libxml_use_internal_errors(true);
        $dom->loadXML($svg);
        libxml_use_internal_errors($previousErrors);

        $svgElement = $dom->documentElement;
        if ($svgElement === null || $svgElement->tagName !== 'svg') {
            return $svg;
        }

        foreach ($options as $key => $value) {
            $svgElement->setAttribute($key, $value);
        }

        return $dom->saveXML($svgElement);
    }

    public function __toString(): string
    {
        if ($this->path === null || $this->path === '') {
            return '';
        }
        if ($this->fileProvider->fileExists($this->path) === false) {
            return '';
        }

        $extOverride = $this->isIconThumbnail() === true ? 'png' : null;
        $cached = $this->cacheFile(
            $this->buildCacheFilename($extOverride),
            fn (): ?string => $this->resolveData()
        );

        return $cached !== null ? $cached[1] : '';
    }

    /**
     * Writes the file to the cache directory if it is not there yet.
     *
     * @param callable(): ?string $producer builds the contents when the cache misses
     * @return array{0: string, 1: string}|null [absolutePath, url], null when the producer has nothing
     */
    private function cacheFile(string $cachedFile, callable $producer): ?array
    {
        if ($this->cachePath === null) {
            throw new \RuntimeException('cachePath is not configured. Set cache.path in params.php under blackcube/fileprovider.');
        }
        if ($this->cacheUrl === null) {
            throw new \RuntimeException('cacheUrl is not configured. Set cache.url in params.php under blackcube/fileprovider.');
        }

        $result = null;
        $cachePath = rtrim($this->aliases->get($this->cachePath), '/').'/'.$cachedFile;
        $cacheUrl = rtrim($this->aliases->get($this->cacheUrl), '/').'/'.$cachedFile;

        $cacheDir = dirname($cachePath);
        if (is_dir($cacheDir) === false) {
            mkdir($cacheDir, 0775, true);
        }

        $available = file_exists($cachePath);
        if ($available === false) {
            $data = $producer();
            if ($data !== null) {
                file_put_contents($cachePath, $data);
                $available = true;
            }
        }

        if ($available === true) {
            $result = [
                $cachePath,
                $cacheUrl,
            ];
        }

        return $result;
    }

    private function resolveData(): ?string
    {
        if ($this->thumbnailWidth !== null || $this->thumbnailHeight !== null) {
            return $this->resolveThumbnailData();
        }
        return $this->processedProvider->read($this->path);
    }

    private function resolveThumbnailData(): ?string
    {
        $ext = strtolower(pathinfo($this->path, PATHINFO_EXTENSION));

        if (in_array($ext, self::SVG_EXTENSIONS, true) === true) {
            return $this->fileProvider->read($this->path);
        }

        if (in_array($ext, self::IMAGE_EXTENSIONS, true) === true) {
            return $this->processedProvider
                ->cover($this->thumbnailWidth, $this->thumbnailHeight)
                ->read($this->path);
        }

        $iconPath = $this->resolveIconPath($ext);
        return $iconPath !== null ? file_get_contents($iconPath) : null;
    }

    private function resolveIconPath(string $ext): ?string
    {
        $iconPath = __DIR__.'/resources/filetypes/'.$ext.'.png';
        if (file_exists($iconPath) === true) {
            return $iconPath;
        }
        $fallback = __DIR__.'/resources/filetypes/file.png';
        return file_exists($fallback) === true ? $fallback : null;
    }

    private function isIconThumbnail(): bool
    {
        if ($this->thumbnailWidth === null && $this->thumbnailHeight === null) {
            return false;
        }
        $ext = strtolower(pathinfo($this->path, PATHINFO_EXTENSION));
        return in_array($ext, self::IMAGE_EXTENSIONS, true) === false
            && in_array($ext, self::SVG_EXTENSIONS, true) === false;
    }

    /**
     * Deterministic filename: path/file-<hash>.ext
     * Hash = substr(sha1(relativePath + opsHash), 0, 6)
     */
    private function buildCacheFilename(?string $extensionOverride = null): string
    {
        [, $relativePath] = $this->fileProvider->resolvePath($this->path);

        $hash = substr(sha1($relativePath.$this->opsHash), 0, self::HASH_LENGTH);

        $info = pathinfo($relativePath);
        $dir = ($info['dirname'] !== '.' && $info['dirname'] !== '') ? $info['dirname'].'/' : '';
        $ext = $extensionOverride ?? ($info['extension'] ?? '');
        $extSuffix = $ext !== '' ? '.'.$ext : '';

        return $dir.$info['filename'].'-'.$hash.$extSuffix;
    }
}
