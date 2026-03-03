<?php

declare(strict_types=1);

namespace Blackcube\FileProvider\Tests\Provider;

use Blackcube\FileProvider\FileProvider;
use Blackcube\FileProvider\FlysystemProviderInterface;
use Blackcube\FileProvider\Tests\Support\FlysystemHelper;
use Blackcube\FileProvider\Tests\Support\ManagerTester;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;

class ImageProcessorCest
{
    private FlysystemProviderInterface $storageFs;
    private FileProvider $provider;
    private string $testImagePath;

    public function _before(ManagerTester $I): void
    {
        FlysystemHelper::resetTestId();
        $this->storageFs = FlysystemHelper::createFilesystem('storage');

        $this->provider = new FileProvider([
            '@blfs' => $this->storageFs,
        ]);

        // Copy test image to storage
        $this->testImagePath = dirname(__DIR__) . '/data/test-image.jpg';
        $this->provider->write('@blfs/image.jpg', file_get_contents($this->testImagePath));
    }

    public function _after(ManagerTester $I): void
    {
        if (FlysystemHelper::isLocal()) {
            $basePath = $_ENV['FILESYSTEM_LOCAL_PATH'] ?? '@data/files';
            $basePath = FlysystemHelper::resolvePath($basePath);
            FlysystemHelper::cleanupLocal($basePath . '/' . FlysystemHelper::getTestId());
        } else {
            FlysystemHelper::cleanupS3($this->storageFs);
        }
    }

    public function testResizeOnRead(ManagerTester $I): void
    {
        $contents = $this->provider->resize(100, 75)->read('@blfs/image.jpg');

        $imageManager = new ImageManager(new GdDriver());
        $image = $imageManager->read($contents);

        $I->assertSame(100, $image->width());
        $I->assertSame(75, $image->height());
    }

    public function testResizeWidthOnly(ManagerTester $I): void
    {
        $contents = $this->provider->resize(100)->read('@blfs/image.jpg');

        $imageManager = new ImageManager(new GdDriver());
        $image = $imageManager->read($contents);

        $I->assertSame(100, $image->width());
        // Height should be proportional (original is 200x150, so 100x75)
        $I->assertSame(75, $image->height());
    }

    public function testRotateOnRead(ManagerTester $I): void
    {
        // Original is 200x150, rotating 90 degrees should make it 150x200
        $contents = $this->provider->rotate(90)->read('@blfs/image.jpg');

        $imageManager = new ImageManager(new GdDriver());
        $image = $imageManager->read($contents);

        $I->assertSame(150, $image->width());
        $I->assertSame(200, $image->height());
    }

    public function testGreyscaleOnRead(ManagerTester $I): void
    {
        $contents = $this->provider->greyscale()->read('@blfs/image.jpg');

        $imageManager = new ImageManager(new GdDriver());
        $image = $imageManager->read($contents);

        // Check that image is greyscale by sampling a pixel
        // In a greyscale image, R, G, B values should be equal
        $color = $image->pickColor(10, 10);
        $I->assertSame($color->red()->toInt(), $color->green()->toInt());
        $I->assertSame($color->green()->toInt(), $color->blue()->toInt());
    }

    public function testFluentChaining(ManagerTester $I): void
    {
        $contents = $this->provider
            ->resize(100, null)
            ->greyscale()
            ->quality(80)
            ->read('@blfs/image.jpg');

        $imageManager = new ImageManager(new GdDriver());
        $image = $imageManager->read($contents);

        $I->assertSame(100, $image->width());
        // Check greyscale
        $color = $image->pickColor(10, 10);
        $I->assertSame($color->red()->toInt(), $color->green()->toInt());
    }

    public function testImmutableFluent(ManagerTester $I): void
    {
        $original = $this->provider;
        $resized = $original->resize(100, 75);
        $greyscale = $resized->greyscale();

        // Each should be a different instance
        $I->assertNotSame($original, $resized);
        $I->assertNotSame($resized, $greyscale);

        // Original should still read full size
        $contents = $original->read('@blfs/image.jpg');
        $imageManager = new ImageManager(new GdDriver());
        $image = $imageManager->read($contents);
        $I->assertSame(200, $image->width());
        $I->assertSame(150, $image->height());
    }

    public function testProcessorsOnWrite(ManagerTester $I): void
    {
        $originalContents = file_get_contents($this->testImagePath);

        // Write with resize (use dimensions that divide evenly)
        $this->provider->resize(100, 75)->write('@blfs/resized.jpg', $originalContents);

        // Read back without processors
        $contents = $this->provider->read('@blfs/resized.jpg');

        $imageManager = new ImageManager(new GdDriver());
        $image = $imageManager->read($contents);

        $I->assertSame(100, $image->width());
        $I->assertSame(75, $image->height());
    }

    public function testNoProcessingWithoutIntervention(ManagerTester $I): void
    {
        // Create a provider that simulates no ImageManager
        // Since ImageManager IS installed, this test verifies non-image files pass through
        $textContent = 'This is plain text';
        $this->provider->write('@blfs/text.txt', $textContent);

        // Apply resize (should be ignored for non-image)
        $contents = $this->provider->resize(100, 100)->read('@blfs/text.txt');

        $I->assertSame($textContent, $contents);
    }

    public function testCropOnRead(ManagerTester $I): void
    {
        $contents = $this->provider->crop(50, 50)->read('@blfs/image.jpg');

        $imageManager = new ImageManager(new GdDriver());
        $image = $imageManager->read($contents);

        $I->assertSame(50, $image->width());
        $I->assertSame(50, $image->height());
    }

    public function testBlurOnRead(ManagerTester $I): void
    {
        $contents = $this->provider->blur(10)->read('@blfs/image.jpg');

        $imageManager = new ImageManager(new GdDriver());
        $image = $imageManager->read($contents);

        // Just verify it's still a valid image of same size
        $I->assertSame(200, $image->width());
        $I->assertSame(150, $image->height());
    }

    public function testFlipHorizontal(ManagerTester $I): void
    {
        $contents = $this->provider->flip('horizontal')->read('@blfs/image.jpg');

        $imageManager = new ImageManager(new GdDriver());
        $image = $imageManager->read($contents);

        // Verify dimensions are preserved
        $I->assertSame(200, $image->width());
        $I->assertSame(150, $image->height());

        // After horizontal flip (flop), left side (was red) should now be blue
        // Allow small tolerance for JPEG compression artifacts
        $leftColor = $image->pickColor(10, 75);
        $I->assertLessThan(10, $leftColor->red()->toInt());
        $I->assertLessThan(10, $leftColor->green()->toInt());
        $I->assertGreaterThan(245, $leftColor->blue()->toInt());
    }

    public function testQualityOnRead(ManagerTester $I): void
    {
        $highQuality = $this->provider->quality(100)->read('@blfs/image.jpg');
        $lowQuality = $this->provider->quality(10)->read('@blfs/image.jpg');

        // Lower quality should result in smaller file size
        $I->assertLessThan(strlen($highQuality), strlen($lowQuality));
    }
}
