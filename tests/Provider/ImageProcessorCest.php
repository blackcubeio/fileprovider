<?php

declare(strict_types=1);

/**
 * ImageProcessorCest.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\FileProvider\Tests\Provider;

use Blackcube\FileProvider\FileProvider;
use Blackcube\FileProvider\Interfaces\FlysystemProviderInterface;
use Blackcube\FileProvider\Tests\Support\FlysystemHelper;
use Blackcube\FileProvider\Tests\Support\ManagerTester;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Yiisoft\Aliases\Aliases;

class ImageProcessorCest
{
    private FlysystemProviderInterface $storageFs;
    private FileProvider $provider;
    private string $testImagePath;

    public function _before(ManagerTester $I): void
    {
        FlysystemHelper::resetTestId();
        $this->storageFs = FlysystemHelper::createFilesystem('storage');

        $this->provider = new FileProvider(new Aliases());
        $this->provider->addFilesystem('@blfs', $this->storageFs);

        $this->testImagePath = dirname(__DIR__).'/data/test-image.jpg';
        $this->provider->write('@blfs/image.jpg', file_get_contents($this->testImagePath));
    }

    public function _after(ManagerTester $I): void
    {
        if (FlysystemHelper::isLocal()) {
            $basePath = $_ENV['FILESYSTEM_LOCAL_PATH'] ?? '@data/files';
            $basePath = FlysystemHelper::resolvePath($basePath);
            FlysystemHelper::cleanupLocal($basePath.'/'.FlysystemHelper::getTestId());
        } else {
            FlysystemHelper::cleanupS3($this->storageFs);
        }
    }

    public function testCoverOnRead(ManagerTester $I): void
    {
        $contents = $this->provider->cover(100, 75)->read('@blfs/image.jpg');

        $imageManager = new ImageManager(new GdDriver());
        $image = $imageManager->decode($contents);

        $I->assertSame(100, $image->width());
        $I->assertSame(75, $image->height());
    }

    public function testScaleWidthOnly(ManagerTester $I): void
    {
        $contents = $this->provider->scale(100)->read('@blfs/image.jpg');

        $imageManager = new ImageManager(new GdDriver());
        $image = $imageManager->decode($contents);

        $I->assertSame(100, $image->width());
        $I->assertSame(75, $image->height());
    }

    public function testRotateOnRead(ManagerTester $I): void
    {
        $contents = $this->provider->rotate(90)->read('@blfs/image.jpg');

        $imageManager = new ImageManager(new GdDriver());
        $image = $imageManager->decode($contents);

        $I->assertSame(150, $image->width());
        $I->assertSame(200, $image->height());
    }

    public function testGreyscaleOnRead(ManagerTester $I): void
    {
        $contents = $this->provider->grayscale()->read('@blfs/image.jpg');

        $imageManager = new ImageManager(new GdDriver());
        $image = $imageManager->decode($contents);

        $color = $image->colorAt(10, 10);
        $I->assertSame($color->red()->value(), $color->green()->value());
        $I->assertSame($color->green()->value(), $color->blue()->value());
    }

    public function testFluentChaining(ManagerTester $I): void
    {
        $contents = $this->provider
            ->scale(100)
            ->grayscale()
            ->quality(80)
            ->read('@blfs/image.jpg');

        $imageManager = new ImageManager(new GdDriver());
        $image = $imageManager->decode($contents);

        $I->assertSame(100, $image->width());
        $color = $image->colorAt(10, 10);
        $I->assertSame($color->red()->value(), $color->green()->value());
    }

    public function testImmutableFluent(ManagerTester $I): void
    {
        $original = $this->provider;
        $covered = $original->cover(100, 75);
        $grayscale = $covered->grayscale();

        $I->assertNotSame($original, $covered);
        $I->assertNotSame($covered, $grayscale);

        $contents = $original->read('@blfs/image.jpg');
        $imageManager = new ImageManager(new GdDriver());
        $image = $imageManager->decode($contents);
        $I->assertSame(200, $image->width());
        $I->assertSame(150, $image->height());
    }

    public function testProcessorsOnWrite(ManagerTester $I): void
    {
        $originalContents = file_get_contents($this->testImagePath);

        $this->provider->cover(100, 75)->write('@blfs/resized.jpg', $originalContents);

        $contents = $this->provider->read('@blfs/resized.jpg');

        $imageManager = new ImageManager(new GdDriver());
        $image = $imageManager->decode($contents);

        $I->assertSame(100, $image->width());
        $I->assertSame(75, $image->height());
    }

    public function testNoProcessingWithoutIntervention(ManagerTester $I): void
    {
        $textContent = 'This is plain text';
        $this->provider->write('@blfs/text.txt', $textContent);

        $contents = $this->provider->cover(100, 100)->read('@blfs/text.txt');

        $I->assertSame($textContent, $contents);
    }

    public function testCropOnRead(ManagerTester $I): void
    {
        $contents = $this->provider->crop(50, 50)->read('@blfs/image.jpg');

        $imageManager = new ImageManager(new GdDriver());
        $image = $imageManager->decode($contents);

        $I->assertSame(50, $image->width());
        $I->assertSame(50, $image->height());
    }

    public function testBlurOnRead(ManagerTester $I): void
    {
        $contents = $this->provider->blur(10)->read('@blfs/image.jpg');

        $imageManager = new ImageManager(new GdDriver());
        $image = $imageManager->decode($contents);

        $I->assertSame(200, $image->width());
        $I->assertSame(150, $image->height());
    }

    public function testFlipHorizontal(ManagerTester $I): void
    {
        $contents = $this->provider->flip('horizontal')->read('@blfs/image.jpg');

        $imageManager = new ImageManager(new GdDriver());
        $image = $imageManager->decode($contents);

        $I->assertSame(200, $image->width());
        $I->assertSame(150, $image->height());

        $leftColor = $image->colorAt(10, 75);
        $I->assertLessThan(10, $leftColor->red()->value());
        $I->assertLessThan(10, $leftColor->green()->value());
        $I->assertGreaterThan(245, $leftColor->blue()->value());
    }

    public function testQualityOnRead(ManagerTester $I): void
    {
        $highQuality = $this->provider->quality(100)->read('@blfs/image.jpg');
        $lowQuality = $this->provider->quality(10)->read('@blfs/image.jpg');

        $I->assertLessThan(strlen($highQuality), strlen($lowQuality));
    }

    public function testFormatAloneConverts(ManagerTester $I): void
    {
        $contents = $this->provider->format('png')->read('@blfs/image.jpg');

        $I->assertStringStartsWith("\x89PNG", $contents);
    }
}
