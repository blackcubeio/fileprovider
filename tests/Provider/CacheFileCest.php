<?php

declare(strict_types=1);

/**
 * CacheFileCest.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\FileProvider\Tests\Provider;

use Blackcube\FileProvider\CacheFile;
use Blackcube\FileProvider\FileProvider;
use Blackcube\FileProvider\Interfaces\FlysystemProviderInterface;
use Blackcube\FileProvider\Tests\Support\FlysystemHelper;
use Blackcube\FileProvider\Tests\Support\ManagerTester;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Yiisoft\Aliases\Aliases;

class CacheFileCest
{
    private FlysystemProviderInterface $storageFs;
    private FileProvider $provider;
    private Aliases $aliases;
    private string $cacheDir;

    public function _before(ManagerTester $I): void
    {
        FlysystemHelper::resetTestId();
        $this->storageFs = FlysystemHelper::createFilesystem('storage');

        $this->provider = new FileProvider(new Aliases());
        $this->provider->addFilesystem('@blfs', $this->storageFs);

        $this->cacheDir = sys_get_temp_dir().'/cachefile-test-'.uniqid();
        mkdir($this->cacheDir, 0777, true);

        $this->aliases = new Aliases([
            '@cache' => $this->cacheDir,
            '@cacheUrl' => '/cache',
        ]);

        $this->provider->write(
            '@blfs/simple.svg',
            file_get_contents(dirname(__DIR__).'/data/test-simple.svg')
        );
        $this->provider->write(
            '@blfs/svgrepo.svg',
            file_get_contents(dirname(__DIR__).'/data/test-svgrepo.svg')
        );
        $this->provider->write(
            '@blfs/image.jpg',
            file_get_contents(dirname(__DIR__).'/data/test-image.jpg')
        );
    }

    public function _after(ManagerTester $I): void
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

    private function setPath(CacheFile $cacheFile, ?string $path): void
    {
        $ref = new \ReflectionProperty(CacheFile::class, 'path');
        $ref->setValue($cacheFile, $path);
    }


    public function testSvgReturnsInlineContent(ManagerTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/simple.svg');

        $result = $cacheFile->svg();

        $I->assertStringContainsString('<svg', $result);
        $I->assertStringContainsString('<circle', $result);
        $I->assertStringContainsString('fill="red"', $result);
    }

    public function testSvgWithClassOption(ManagerTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/simple.svg');

        $result = $cacheFile->svg(['class' => 'w-5 h-5 text-primary']);

        $I->assertStringContainsString('class="w-5 h-5 text-primary"', $result);
        $I->assertStringContainsString('<circle', $result);
    }

    public function testSvgWithMultipleOptions(ManagerTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/simple.svg');

        $result = $cacheFile->svg([
            'class' => 'icon',
            'id' => 'my-icon',
            'data-tooltip' => 'Hello',
        ]);

        $I->assertStringContainsString('class="icon"', $result);
        $I->assertStringContainsString('id="my-icon"', $result);
        $I->assertStringContainsString('data-tooltip="Hello"', $result);
    }

    public function testSvgWithoutOptionsReturnsOriginal(ManagerTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/simple.svg');

        $result = $cacheFile->svg();
        $original = file_get_contents(dirname(__DIR__).'/data/test-simple.svg');

        $I->assertStringContainsString('<circle cx="50" cy="50" r="40" fill="red"/>', $result);
        $I->assertStringContainsString('viewBox="0 0 100 100"', $result);
    }

    public function testSvgFileReturnsUrl(ManagerTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/simple.svg');

        $result = $cacheFile->svgFile();

        $I->assertStringContainsString('/cache/simple.svg', $result);
        $I->assertStringNotContainsString('<svg', $result);
    }

    public function testSvgInlineFalseReturnsUrl(ManagerTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/simple.svg');

        $result = $cacheFile->svg(inline: false);

        $I->assertEquals($cacheFile->svgFile(), $result);
    }


    public function testSvgRepoReturnsInlineContent(ManagerTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/svgrepo.svg');

        $result = $cacheFile->svg();

        $I->assertStringContainsString('<svg', $result);
        $I->assertStringContainsString('viewBox="0 0 36 36"', $result);
        $I->assertStringContainsString('fill="#F4900C"', $result);
        $I->assertStringContainsString('fill="#E1E8ED"', $result);
    }

    public function testSvgRepoWithClassOverridesExisting(ManagerTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/svgrepo.svg');

        $result = $cacheFile->svg(['class' => 'w-5 h-5 text-primary-700']);

        $I->assertStringContainsString('class="w-5 h-5 text-primary-700"', $result);
        $I->assertStringNotContainsString('iconify iconify--twemoji', $result);
    }

    public function testSvgRepoWithOptionsPreservesExistingAttributes(ManagerTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/svgrepo.svg');

        $result = $cacheFile->svg(['class' => 'custom']);

        $I->assertStringContainsString('aria-hidden="true"', $result);
        $I->assertStringContainsString('role="img"', $result);
        $I->assertStringContainsString('preserveAspectRatio="xMidYMid meet"', $result);
        $I->assertStringContainsString('<path', $result);
    }

    public function testSvgRepoWithMultipleOptionsOverridesAndAdds(ManagerTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/svgrepo.svg');

        $result = $cacheFile->svg([
            'class' => 'icon-small',
            'width' => '24px',
            'height' => '24px',
            'aria-label' => 'Weather icon',
        ]);

        $I->assertStringContainsString('class="icon-small"', $result);
        $I->assertStringContainsString('width="24px"', $result);
        $I->assertStringContainsString('height="24px"', $result);
        $I->assertStringContainsString('aria-label="Weather icon"', $result);
        $I->assertStringNotContainsString('800px', $result);
    }

    public function testSvgRepoFileReturnsUrl(ManagerTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/svgrepo.svg');

        $result = $cacheFile->svgFile();

        $I->assertStringContainsString('/cache/svgrepo.svg', $result);
        $I->assertStringNotContainsString('<svg', $result);
    }


    public function testSvgWithEmptyPathReturnsEmpty(ManagerTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '');

        $I->assertSame('', $cacheFile->svg());
    }

    public function testSvgWithNullPathReturnsEmpty(ManagerTester $I): void
    {
        $cacheFile = $this->createCacheFile();

        $I->assertSame('', $cacheFile->svg());
    }

    public function testSvgWithNonExistentFileReturnsEmpty(ManagerTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/does-not-exist.svg');

        $I->assertSame('', $cacheFile->svg());
    }

    public function testSvgWithoutCachePathThrows(ManagerTester $I): void
    {
        $cacheFile = $this->createCacheFile(cachePath: null);
        $this->setPath($cacheFile, '@blfs/simple.svg');

        $I->expectThrowable(\RuntimeException::class, function () use ($cacheFile) {
            $cacheFile->svg();
        });
    }

    public function testSvgWithoutCacheUrlThrows(ManagerTester $I): void
    {
        $cacheFile = $this->createCacheFile(cacheUrl: null);
        $this->setPath($cacheFile, '@blfs/simple.svg');

        $I->expectThrowable(\RuntimeException::class, function () use ($cacheFile) {
            $cacheFile->svg();
        });
    }

    public function testSvgCachesFileOnDisk(ManagerTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/simple.svg');

        $cacheFile->svg();

        $cachedPath = $this->cacheDir.'/simple.svg';
        $I->assertFileExists($cachedPath);
    }

    public function testSvgUsesExistingCache(ManagerTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/simple.svg');

        $cacheFile->svg();

        $cachedPath = $this->cacheDir.'/simple.svg';
        file_put_contents($cachedPath, '<svg><text>cached</text></svg>');

        $cacheFile2 = $this->createCacheFile();
        $this->setPath($cacheFile2, '@blfs/simple.svg');
        $result = $cacheFile2->svg();

        $I->assertStringContainsString('<text>cached</text>', $result);
    }

    public function testThumbnailWithWidthOnlyIsSquare(ManagerTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/image.jpg');

        $url = (string) $cacheFile->thumbnail(120);

        $I->assertNotSame('', $url);
        [$width, $height] = $this->cachedImageSize($url);
        $I->assertSame(120, $width);
        $I->assertSame(120, $height);
    }

    public function testThumbnailWithHeightOnlyIsSquare(ManagerTester $I): void
    {
        $cacheFile = $this->createCacheFile();
        $this->setPath($cacheFile, '@blfs/image.jpg');

        $url = (string) $cacheFile->thumbnail(null, 90);

        $I->assertNotSame('', $url);
        [$width, $height] = $this->cachedImageSize($url);
        $I->assertSame(90, $width);
        $I->assertSame(90, $height);
    }

    /**
     * Dimensions of the file written in the cache directory for the given url.
     *
     * @return array{0: int, 1: int} [width, height]
     */
    private function cachedImageSize(string $url): array
    {
        $imageManager = new ImageManager(new GdDriver());
        $image = $imageManager->decode(file_get_contents($this->cacheDir.'/'.basename($url)));

        return [
            $image->width(),
            $image->height(),
        ];
    }
}
