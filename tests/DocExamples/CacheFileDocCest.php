<?php

declare(strict_types=1);

/**
 * CacheFileDocCest.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\FileProvider\Tests\DocExamples;

use Blackcube\FileProvider\CacheFile;
use Blackcube\FileProvider\FileProvider;
use Blackcube\FileProvider\Interfaces\FlysystemProviderInterface;
use Blackcube\FileProvider\Tests\Support\DocExamplesTester;
use Blackcube\FileProvider\Tests\Support\FlysystemHelper;
use Yiisoft\Aliases\Aliases;

/**
 * Tests covering code examples from:
 * - docs/api-cachefile.md
 * - docs/installation-standalone.md (CacheFile section)
 */
class CacheFileDocCest
{
    private FlysystemProviderInterface $storageFs;
    private FileProvider $provider;
    private Aliases $aliases;
    private string $cacheDir;

    public function _before(DocExamplesTester $I): void
    {
        FlysystemHelper::resetTestId();
        $this->storageFs = FlysystemHelper::createFilesystem('storage');

        $this->provider = new FileProvider(new Aliases());
        $this->provider->addFilesystem('@blfs', $this->storageFs);

        $this->cacheDir = sys_get_temp_dir().'/doctest-cache-'.uniqid();
        mkdir($this->cacheDir, 0777, true);

        $this->aliases = new Aliases([
            '@cache' => $this->cacheDir,
            '@cacheUrl' => '/cache',
        ]);

        $this->provider->write(
            '@blfs/images/photo.jpg',
            file_get_contents(dirname(__DIR__).'/data/test-image.jpg')
        );
        $this->provider->write(
            '@blfs/icons/check.svg',
            file_get_contents(dirname(__DIR__).'/data/test-simple.svg')
        );
        $this->provider->write(
            '@blfs/icons/weather.svg',
            file_get_contents(dirname(__DIR__).'/data/test-svgrepo.svg')
        );
    }

    public function _after(DocExamplesTester $I): void
    {
        if (is_dir($this->cacheDir) === true) {
            FlysystemHelper::cleanupLocal($this->cacheDir);
        }

        if (FlysystemHelper::isLocal()) {
            $basePath = $_ENV['FILESYSTEM_LOCAL_PATH'] ?? '@data/files';
            $basePath = FlysystemHelper::resolvePath($basePath);
            FlysystemHelper::cleanupLocal($basePath.'/'.FlysystemHelper::getTestId());
        } else {
            FlysystemHelper::cleanupS3($this->storageFs);
        }
    }

    private function createCacheFile(?string $cachePath = '@cache', ?string $cacheUrl = '@cacheUrl'): CacheFile
    {
        return new CacheFile($this->provider, $this->aliases, $cachePath, $cacheUrl);
    }

    private function setPath(CacheFile $cacheFile, string $path): void
    {
        $ref = new \ReflectionProperty(CacheFile::class, 'path');
        $ref->setValue($cacheFile, $path);
    }


    public function testConstructor(DocExamplesTester $I): void
    {
        $cacheFile = new CacheFile(
            fileProvider: $this->provider,
            aliases: $this->aliases,
            cachePath: '@cache',
            cacheUrl: '@cacheUrl',
        );
        $I->assertInstanceOf(CacheFile::class, $cacheFile);
    }


    public function testToStringReturnsUrl(DocExamplesTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/images/photo.jpg');

        $url = (string) $cacheFile;
        $I->assertStringContainsString('/cache/', $url);
        $I->assertStringContainsString('photo', $url);
        $I->assertStringContainsString('.jpg', $url);
    }

    public function testToStringEmptyOnMissingFile(DocExamplesTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/missing.jpg');

        $I->assertSame('', (string) $cacheFile);
    }


    public function testScale(DocExamplesTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/images/photo.jpg');

        $url = (string) $cacheFile->scale(100);
        $I->assertStringContainsString('/cache/', $url);
        $I->assertNotEmpty($url);
    }

    public function testCover(DocExamplesTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/images/photo.jpg');

        $url = (string) $cacheFile->cover(100, 75);
        $I->assertNotEmpty($url);
    }

    public function testPad(DocExamplesTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/images/photo.jpg');

        $url = (string) $cacheFile->pad(100, 100, '#ffffff');
        $I->assertNotEmpty($url);
    }

    public function testCrop(DocExamplesTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/images/photo.jpg');

        $url = (string) $cacheFile->crop(50, 50);
        $I->assertNotEmpty($url);
    }

    public function testRotate(DocExamplesTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/images/photo.jpg');

        $url = (string) $cacheFile->rotate(90);
        $I->assertNotEmpty($url);
    }

    public function testFlip(DocExamplesTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/images/photo.jpg');

        $url = (string) $cacheFile->flip('horizontal');
        $I->assertNotEmpty($url);
    }

    public function testGreyscale(DocExamplesTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/images/photo.jpg');

        $url = (string) $cacheFile->grayscale();
        $I->assertNotEmpty($url);
    }

    public function testBlur(DocExamplesTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/images/photo.jpg');

        $url = (string) $cacheFile->blur(10);
        $I->assertNotEmpty($url);
    }

    public function testQuality(DocExamplesTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/images/photo.jpg');

        $url = (string) $cacheFile->quality(75);
        $I->assertNotEmpty($url);
    }

    public function testFormat(DocExamplesTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/images/photo.jpg');

        $url = (string) $cacheFile->format('webp');
        $I->assertNotEmpty($url);
    }


    public function testWebpShortcut(DocExamplesTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/images/photo.jpg');

        $url = (string) $cacheFile->webp();
        $I->assertNotEmpty($url);
    }

    public function testPngShortcut(DocExamplesTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/images/photo.jpg');

        $url = (string) $cacheFile->png();
        $I->assertNotEmpty($url);
    }

    public function testJpgShortcut(DocExamplesTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/images/photo.jpg');

        $url = (string) $cacheFile->jpg();
        $I->assertNotEmpty($url);
    }


    public function testChaining(DocExamplesTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/images/photo.jpg');

        $url = (string) $cacheFile
            ->cover(100, 75)
            ->grayscale()
            ->quality(85)
            ->webp();
        $I->assertNotEmpty($url);
    }


    public function testThumbnailImage(DocExamplesTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/images/photo.jpg');

        $url = (string) $cacheFile->thumbnail(100, 100);
        $I->assertNotEmpty($url);
    }

    public function testThumbnailSvg(DocExamplesTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/icons/check.svg');

        $url = (string) $cacheFile->thumbnail(100, 100);
        $I->assertNotEmpty($url);
    }


    public function testSvgInline(DocExamplesTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/icons/check.svg');

        $svgContent = $cacheFile->svg();
        $I->assertStringContainsString('<svg', $svgContent);
        $I->assertStringContainsString('<circle', $svgContent);
    }

    public function testSvgWithClass(DocExamplesTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/icons/check.svg');

        $svgContent = $cacheFile->svg([
            'class' => 'w-5 h-5 text-primary-700',
        ]);
        $I->assertStringContainsString('class="w-5 h-5 text-primary-700"', $svgContent);
    }

    public function testSvgWithMultipleAttributes(DocExamplesTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/icons/check.svg');

        $svgContent = $cacheFile->svg([
            'class' => 'icon',
            'aria-hidden' => 'true',
            'width' => '24px',
            'height' => '24px',
        ]);
        $I->assertStringContainsString('class="icon"', $svgContent);
        $I->assertStringContainsString('aria-hidden="true"', $svgContent);
        $I->assertStringContainsString('width="24px"', $svgContent);
        $I->assertStringContainsString('height="24px"', $svgContent);
    }

    public function testSvgInlineFalse(DocExamplesTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/icons/check.svg');

        $url = $cacheFile->svg(inline: false);
        $I->assertStringContainsString('/cache/', $url);
        $I->assertStringNotContainsString('<svg', $url);
    }

    public function testSvgFile(DocExamplesTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/icons/check.svg');

        $url = $cacheFile->svgFile();
        $I->assertStringContainsString('/cache/', $url);
        $I->assertStringNotContainsString('<svg', $url);
    }

    public function testSvgWithRealWorldSvg(DocExamplesTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/icons/weather.svg');

        $svgContent = $cacheFile->svg([
            'class' => 'w-5 h-5 text-primary-700 dark:text-primary-400',
        ]);
        $I->assertStringContainsString('class="w-5 h-5 text-primary-700 dark:text-primary-400"', $svgContent);
        $I->assertStringNotContainsString('iconify iconify--twemoji', $svgContent);
        $I->assertStringContainsString('aria-hidden="true"', $svgContent);
        $I->assertStringContainsString('role="img"', $svgContent);
    }


    public function testFluentImmutability(DocExamplesTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/images/photo.jpg');

        $scaled = $cacheFile->scale(100);
        $covered = $cacheFile->cover(100, 75);

        $I->assertNotSame($cacheFile, $scaled);
        $I->assertNotSame($cacheFile, $covered);
        $I->assertNotSame($scaled, $covered);
    }


    public function testThrowsWithoutCachePath(DocExamplesTester $I): void
    {
        $cacheFile = $this->createCacheFile(cachePath: null);
        $this->setPath($cacheFile, '@blfs/images/photo.jpg');

        $I->expectThrowable(\RuntimeException::class, function () use ($cacheFile) {
            (string) $cacheFile;
        });
    }

    public function testThrowsWithoutCacheUrl(DocExamplesTester $I): void
    {
        $cacheFile = $this->createCacheFile(cacheUrl: null);
        $this->setPath($cacheFile, '@blfs/images/photo.jpg');

        $I->expectThrowable(\RuntimeException::class, function () use ($cacheFile) {
            (string) $cacheFile;
        });
    }
}
