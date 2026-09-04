<?php

declare(strict_types=1);

/**
 * ImageManagerFactoryCest.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\FileProvider\Tests\Provider;

use Blackcube\FileProvider\Image\ImageManagerFactory;
use Blackcube\FileProvider\Tests\Support\ManagerTester;
use Intervention\Image\ImageManager;

class ImageManagerFactoryCest
{
    public function testCreateWithAutoDetection(ManagerTester $I): void
    {
        $manager = ImageManagerFactory::create();

        $I->assertInstanceOf(ImageManager::class, $manager);
    }

    public function testCreateWithGdDriver(ManagerTester $I): void
    {
        $manager = ImageManagerFactory::create('gd');

        $I->assertInstanceOf(ImageManager::class, $manager);
    }

    public function testCreateWithImagickDriverIfAvailable(ManagerTester $I): void
    {
        if (extension_loaded('imagick') === false) {
            $I->markTestSkipped('Imagick extension not available');
            return;
        }

        $manager = ImageManagerFactory::create('imagick');

        $I->assertInstanceOf(ImageManager::class, $manager);
    }

    public function testCreateWithVipsDriverIfAvailable(ManagerTester $I): void
    {
        if (extension_loaded('vips') === false) {
            $I->markTestSkipped('Vips extension not available');
            return;
        }

        $manager = ImageManagerFactory::create('vips');

        $I->assertInstanceOf(ImageManager::class, $manager);
    }

    public function testCreateWithUnavailableDriverThrowsException(ManagerTester $I): void
    {
        if (extension_loaded('vips') === true) {
            $I->markTestSkipped('Vips is available, cannot test unavailable driver');
            return;
        }

        $I->expectThrowable(\InvalidArgumentException::class, function () {
            ImageManagerFactory::create('vips');
        });
    }

    public function testCreateWithUnknownDriverThrowsException(ManagerTester $I): void
    {
        $I->expectThrowable(\InvalidArgumentException::class, function () {
            ImageManagerFactory::create('unknown');
        });
    }

    public function testManagerCanReadImage(ManagerTester $I): void
    {
        $manager = ImageManagerFactory::create('gd');

        $img = imagecreatetruecolor(100, 100);
        ob_start();
        imagejpeg($img, null, 90);
        $contents = ob_get_clean();
        imagedestroy($img);

        $image = $manager->decode($contents);

        $I->assertSame(100, $image->width());
        $I->assertSame(100, $image->height());
    }
}
