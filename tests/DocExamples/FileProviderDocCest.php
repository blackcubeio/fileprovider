<?php

declare(strict_types=1);

/**
 * FileProviderDocCest.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\FileProvider\Tests\DocExamples;

use Blackcube\FileProvider\Exception\UnknownFilesystemException;
use Blackcube\FileProvider\FileProvider;
use Blackcube\FileProvider\Flysystem\FlysystemLocal;
use Blackcube\FileProvider\Image\ImageManagerFactory;
use Blackcube\FileProvider\Interfaces\FileProviderInterface;
use Blackcube\FileProvider\Interfaces\FlysystemProviderInterface;
use Blackcube\FileProvider\Tests\Support\DocExamplesTester;
use Blackcube\FileProvider\Tests\Support\FlysystemHelper;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Yiisoft\Aliases\Aliases;

/**
 * Tests covering code examples from:
 * - README.md (Quickstart)
 * - docs/installation-standalone.md
 * - docs/api-fileprovider.md
 */
class FileProviderDocCest
{
    private FlysystemProviderInterface $tmpFs;
    private FlysystemProviderInterface $storageFs;
    private FileProvider $provider;

    public function _before(DocExamplesTester $I): void
    {
        FlysystemHelper::resetTestId();
        $this->tmpFs = FlysystemHelper::createFilesystem('tmp');
        $this->storageFs = FlysystemHelper::createFilesystem('storage');

        $this->provider = new FileProvider(new Aliases());
        $this->provider->addFilesystem('@bltmp', $this->tmpFs);
        $this->provider->addFilesystem('@blfs', $this->storageFs);

        $this->provider->write(
            '@blfs/image.jpg',
            file_get_contents(dirname(__DIR__).'/data/test-image.jpg')
        );
    }

    public function _after(DocExamplesTester $I): void
    {
        if (FlysystemHelper::isLocal()) {
            $basePath = $_ENV['FILESYSTEM_LOCAL_PATH'] ?? '@data/files';
            $basePath = FlysystemHelper::resolvePath($basePath);
            FlysystemHelper::cleanupLocal($basePath.'/'.FlysystemHelper::getTestId());
        } else {
            FlysystemHelper::cleanupS3($this->tmpFs);
            FlysystemHelper::cleanupS3($this->storageFs);
        }
    }


    public function testReadmeQuickstart(DocExamplesTester $I): void
    {
        $content = 'binary data';
        $this->provider->write('@bltmp/upload.jpg', $content);

        $this->provider->move('@bltmp/upload.jpg', '@blfs/images/photo.jpg');

        $result = $this->provider->read('@blfs/images/photo.jpg');
        $I->assertSame($content, $result);
    }

    public function testReadmeImageProcessing(DocExamplesTester $I): void
    {
        $this->provider->write('@blfs/images/photo.jpg', file_get_contents(dirname(__DIR__).'/data/test-image.jpg'));
        $thumbnail = $this->provider->cover(200, 200)->read('@blfs/images/photo.jpg');
        $I->assertNotEmpty($thumbnail);

        $imageManager = new ImageManager(new GdDriver());
        $image = $imageManager->decode($thumbnail);
        $I->assertSame(200, $image->width());
        $I->assertSame(200, $image->height());
    }


    public function testConstructorWithDefaults(DocExamplesTester $I): void
    {
        $provider = new FileProvider(
            aliases: new Aliases(),
            filesystems: [],
            defaultAlias: '@blfs',
            imageDriver: null,
        );

        $I->assertInstanceOf(FileProvider::class, $provider);
        $I->assertInstanceOf(FileProviderInterface::class, $provider);
        $I->assertInstanceOf(FlysystemProviderInterface::class, $provider);
    }


    public function testAddAndGetFilesystem(DocExamplesTester $I): void
    {

        $fs = $this->provider->getFilesystem('@blfs');
        $I->assertInstanceOf(FlysystemProviderInterface::class, $fs);
    }

    public function testHasFilesystem(DocExamplesTester $I): void
    {
        $I->assertTrue($this->provider->hasFilesystem('@blfs'));
        $I->assertFalse($this->provider->hasFilesystem('@unknown'));
    }

    public function testGetAliases(DocExamplesTester $I): void
    {
        $aliases = $this->provider->getAliases();
        $I->assertContains('@bltmp', $aliases);
        $I->assertContains('@blfs', $aliases);
    }

    public function testGetFilesystemThrowsOnUnknown(DocExamplesTester $I): void
    {
        $I->expectThrowable(UnknownFilesystemException::class, function () {
            $this->provider->getFilesystem('@unknown');
        });
    }


    public function testResolvePath(DocExamplesTester $I): void
    {
        [$alias, $relativePath] = $this->provider->resolvePath('@blfs/images/photo.jpg');
        $I->assertSame('@blfs', $alias);
        $I->assertSame('images/photo.jpg', $relativePath);

        [$alias, $relativePath] = $this->provider->resolvePath('no-prefix.txt');
        $I->assertSame('@blfs', $alias);
        $I->assertSame('no-prefix.txt', $relativePath);
    }

    public function testCanHandle(DocExamplesTester $I): void
    {
        $I->assertTrue($this->provider->canHandle('@blfs/file.jpg'));

        $I->assertFalse($this->provider->canHandle('@unknown/file.jpg'));
    }


    public function testWriteAndRead(DocExamplesTester $I): void
    {
        $content = 'test content';
        $this->provider->write('@bltmp/upload.jpg', $content);

        $I->assertSame($content, $this->provider->read('@bltmp/upload.jpg'));
    }

    public function testWriteStreamAndReadStream(DocExamplesTester $I): void
    {
        $content = 'stream content';
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);
        $this->provider->writeStream('@blfs/archive.zip', $stream);
        fclose($stream);

        $stream = $this->provider->readStream('@blfs/archive.zip');
        $I->assertIsResource($stream);
        $result = stream_get_contents($stream);
        fclose($stream);
        $I->assertSame($content, $result);
    }

    public function testMoveSameFilesystem(DocExamplesTester $I): void
    {
        $this->provider->write('@blfs/old-name.jpg', 'data');
        $this->provider->move('@blfs/old-name.jpg', '@blfs/new-name.jpg');

        $I->assertFalse($this->provider->fileExists('@blfs/old-name.jpg'));
        $I->assertTrue($this->provider->fileExists('@blfs/new-name.jpg'));
    }

    public function testMoveCrossFilesystem(DocExamplesTester $I): void
    {
        $this->provider->write('@bltmp/upload.jpg', 'cross data');
        $this->provider->move('@bltmp/upload.jpg', '@blfs/moved-photo.jpg');

        $I->assertFalse($this->provider->fileExists('@bltmp/upload.jpg'));
        $I->assertTrue($this->provider->fileExists('@blfs/moved-photo.jpg'));
        $I->assertSame('cross data', $this->provider->read('@blfs/moved-photo.jpg'));
    }

    public function testCopy(DocExamplesTester $I): void
    {
        $this->provider->write('@blfs/original.jpg', 'original');
        $this->provider->copy('@blfs/original.jpg', '@bltmp/backup.jpg');

        $I->assertTrue($this->provider->fileExists('@blfs/original.jpg'));
        $I->assertTrue($this->provider->fileExists('@bltmp/backup.jpg'));
        $I->assertSame('original', $this->provider->read('@bltmp/backup.jpg'));
    }

    public function testDeleteAndDirectoryOps(DocExamplesTester $I): void
    {
        $this->provider->write('@blfs/old-file.jpg', 'to delete');
        $I->assertTrue($this->provider->fileExists('@blfs/old-file.jpg'));
        $this->provider->delete('@blfs/old-file.jpg');
        $I->assertFalse($this->provider->fileExists('@blfs/old-file.jpg'));

        $this->provider->createDirectory('@blfs/images');
        $I->assertTrue($this->provider->directoryExists('@blfs/images'));

        $this->provider->write('@bltmp/chunks/part1.bin', 'chunk');
        $this->provider->deleteDirectory('@bltmp/chunks');
        $I->assertFalse($this->provider->directoryExists('@bltmp/chunks'));
    }

    public function testFileExistsDirectoryExistsHas(DocExamplesTester $I): void
    {
        $I->assertTrue($this->provider->fileExists('@blfs/image.jpg'));
        $I->assertFalse($this->provider->fileExists('@blfs/missing.jpg'));

        $this->provider->createDirectory('@blfs/subdir');
        $I->assertTrue($this->provider->directoryExists('@blfs/subdir'));

        $I->assertTrue($this->provider->has('@blfs/image.jpg'));
        $I->assertTrue($this->provider->has('@blfs/subdir'));
        $I->assertFalse($this->provider->has('@blfs/nothing'));
    }

    public function testMetadata(DocExamplesTester $I): void
    {
        $mime = $this->provider->mimeType('@blfs/image.jpg');
        $I->assertStringContainsString('image', $mime);

        $size = $this->provider->fileSize('@blfs/image.jpg');
        $I->assertGreaterThan(0, $size);

        $timestamp = $this->provider->lastModified('@blfs/image.jpg');
        $I->assertIsInt($timestamp);
    }

    public function testVisibility(DocExamplesTester $I): void
    {
        $visibility = $this->provider->visibility('@blfs/image.jpg');
        $I->assertContains($visibility, ['public', 'private']);
    }

    public function testListContents(DocExamplesTester $I): void
    {
        $this->provider->write('@blfs/list-a.txt', 'a');
        $this->provider->write('@blfs/list-b.txt', 'b');

        $contents = iterator_to_array($this->provider->listContents('@blfs'));
        $I->assertGreaterThanOrEqual(2, count($contents));
    }


    public function testScaleOnRead(DocExamplesTester $I): void
    {
        $scaled = $this->provider->scale(100)->read('@blfs/image.jpg');

        $imageManager = new ImageManager(new GdDriver());
        $image = $imageManager->decode($scaled);
        $I->assertSame(100, $image->width());
    }

    public function testCoverOnRead(DocExamplesTester $I): void
    {
        $covered = $this->provider->cover(100, 75)->read('@blfs/image.jpg');

        $imageManager = new ImageManager(new GdDriver());
        $image = $imageManager->decode($covered);
        $I->assertSame(100, $image->width());
        $I->assertSame(75, $image->height());
    }

    public function testPadOnRead(DocExamplesTester $I): void
    {
        $padded = $this->provider->pad(100, 100, '#ffffff')->read('@blfs/image.jpg');

        $imageManager = new ImageManager(new GdDriver());
        $image = $imageManager->decode($padded);
        $I->assertSame(100, $image->width());
        $I->assertSame(100, $image->height());
    }

    public function testCropOnRead(DocExamplesTester $I): void
    {
        $cropped = $this->provider->crop(50, 50)->read('@blfs/image.jpg');

        $imageManager = new ImageManager(new GdDriver());
        $image = $imageManager->decode($cropped);
        $I->assertSame(50, $image->width());
        $I->assertSame(50, $image->height());
    }

    public function testRotateOnRead(DocExamplesTester $I): void
    {
        $rotated = $this->provider->rotate(90)->read('@blfs/image.jpg');

        $imageManager = new ImageManager(new GdDriver());
        $image = $imageManager->decode($rotated);
        $I->assertSame(150, $image->width());
        $I->assertSame(200, $image->height());
    }

    public function testFlipOnRead(DocExamplesTester $I): void
    {
        $flipped = $this->provider->flip('horizontal')->read('@blfs/image.jpg');

        $imageManager = new ImageManager(new GdDriver());
        $image = $imageManager->decode($flipped);
        $I->assertSame(200, $image->width());
    }

    public function testGreyscaleOnRead(DocExamplesTester $I): void
    {
        $grey = $this->provider->grayscale()->read('@blfs/image.jpg');

        $imageManager = new ImageManager(new GdDriver());
        $image = $imageManager->decode($grey);
        $color = $image->colorAt(10, 10);
        $I->assertSame($color->red()->value(), $color->green()->value());
    }

    public function testBlurOnRead(DocExamplesTester $I): void
    {
        $blurred = $this->provider->blur(10)->read('@blfs/image.jpg');

        $imageManager = new ImageManager(new GdDriver());
        $image = $imageManager->decode($blurred);
        $I->assertSame(200, $image->width());
    }

    public function testQualityOnRead(DocExamplesTester $I): void
    {
        $high = $this->provider->quality(100)->read('@blfs/image.jpg');
        $low = $this->provider->quality(10)->read('@blfs/image.jpg');
        $I->assertLessThan(strlen($high), strlen($low));
    }

    public function testFormatOnRead(DocExamplesTester $I): void
    {
        $webp = $this->provider->format('webp')->read('@blfs/image.jpg');
        $I->assertNotEmpty($webp);
    }

    public function testChainingOnRead(DocExamplesTester $I): void
    {
        $processed = $this->provider
            ->cover(100, 75)
            ->grayscale()
            ->quality(85)
            ->read('@blfs/image.jpg');

        $imageManager = new ImageManager(new GdDriver());
        $image = $imageManager->decode($processed);
        $I->assertSame(100, $image->width());
        $I->assertSame(75, $image->height());
    }

    public function testProcessorsOnWrite(DocExamplesTester $I): void
    {
        $original = file_get_contents(dirname(__DIR__).'/data/test-image.jpg');
        $this->provider
            ->pad(100, 100, '#ffffff')
            ->write('@blfs/padded.jpg', $original);

        $result = $this->provider->read('@blfs/padded.jpg');
        $imageManager = new ImageManager(new GdDriver());
        $image = $imageManager->decode($result);
        $I->assertSame(100, $image->width());
        $I->assertSame(100, $image->height());
    }


    public function testConstants(DocExamplesTester $I): void
    {
        $I->assertSame('@bltmp', FileProvider::ALIAS_TMP);
        $I->assertSame('@blfs', FileProvider::ALIAS_FS);
    }


    public function testFlysystemLocalConstructor(DocExamplesTester $I): void
    {
        $tmpDir = sys_get_temp_dir().'/doctest-local-'.uniqid();
        mkdir($tmpDir, 0777, true);
        $local = new FlysystemLocal($tmpDir);
        $I->assertInstanceOf(FlysystemProviderInterface::class, $local);
        FlysystemHelper::cleanupLocal($tmpDir);
    }


    public function testImageManagerFactoryCreate(DocExamplesTester $I): void
    {
        $manager = ImageManagerFactory::create();
        $I->assertInstanceOf(ImageManager::class, $manager);

        $manager = ImageManagerFactory::create('gd');
        $I->assertInstanceOf(ImageManager::class, $manager);
    }


    public function testFluentImmutability(DocExamplesTester $I): void
    {
        $original = $this->provider;
        $scaled = $original->scale(100);
        $covered = $original->cover(100, 75);

        $I->assertNotSame($original, $scaled);
        $I->assertNotSame($original, $covered);
        $I->assertNotSame($scaled, $covered);
    }
}
