<?php

declare(strict_types=1);

/**
 * ResumableDocCest.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\FileProvider\Tests\DocExamples;

use Blackcube\FileProvider\FileProvider;
use Blackcube\FileProvider\Interfaces\FlysystemProviderInterface;
use Blackcube\FileProvider\Resumable\ResumableConfig;
use Blackcube\FileProvider\Resumable\ResumableService;
use Blackcube\FileProvider\Tests\Support\DocExamplesTester;
use Blackcube\FileProvider\Tests\Support\FlysystemHelper;
use Yiisoft\Aliases\Aliases;

/**
 * Tests covering code examples from:
 * - docs/api-resumable.md
 * - docs/installation-standalone.md (Resumable section)
 */
class ResumableDocCest
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


    public function testResumableConfigConstructor(DocExamplesTester $I): void
    {
        $config = new ResumableConfig(
            chunkSize: 524288,
            uploadEndpoint: '/fileprovider/upload',
            previewEndpoint: '/fileprovider/preview',
            deleteEndpoint: '/fileprovider/delete',
            filetypeIconAlias: null,
            thumbnailWidth: 200,
            thumbnailHeight: 200,
        );

        $I->assertInstanceOf(ResumableConfig::class, $config);
    }

    public function testResumableConfigGetters(DocExamplesTester $I): void
    {
        $config = new ResumableConfig(
            chunkSize: 524288,
            uploadEndpoint: '/fileprovider/upload',
            previewEndpoint: '/fileprovider/preview',
            deleteEndpoint: '/fileprovider/delete',
        );

        $I->assertSame(524288, $config->getChunkSize());
        $I->assertSame('/fileprovider/upload', $config->getUploadEndpoint());
        $I->assertSame('/fileprovider/preview', $config->getPreviewEndpoint());
        $I->assertSame('/fileprovider/delete', $config->getDeleteEndpoint());
        $I->assertSame(200, $config->getThumbnailWidth());
        $I->assertSame(200, $config->getThumbnailHeight());
    }

    public function testResumableConfigEndpointThrowsWhenNull(DocExamplesTester $I): void
    {
        $config = new ResumableConfig(
            uploadEndpoint: null,
        );

        $I->expectThrowable(\RuntimeException::class, function () use ($config) {
            $config->getUploadEndpoint();
        });
    }

    public function testCleanFilename(DocExamplesTester $I): void
    {
        $safe = ResumableConfig::cleanFilename('../../../etc/passwd');
        $I->assertStringNotContainsString('..', $safe);

        $safe = ResumableConfig::cleanFilename('my photo (1).jpg');
        $I->assertSame('my_photo_1_.jpg', $safe);
    }


    public function testResumableServiceConstructor(DocExamplesTester $I): void
    {
        $config = new ResumableConfig(
            uploadEndpoint: '/fileprovider/upload',
            previewEndpoint: '/fileprovider/preview',
            deleteEndpoint: '/fileprovider/delete',
        );

        $service = new ResumableService($this->provider, $config);
        $I->assertInstanceOf(ResumableService::class, $service);
    }

    public function testGetTmpPrefix(DocExamplesTester $I): void
    {
        $config = new ResumableConfig();
        $service = new ResumableService($this->provider, $config);

        $I->assertSame('@bltmp', $service->getTmpPrefix());
    }

    public function testChunkWorkflow(DocExamplesTester $I): void
    {
        $config = new ResumableConfig();
        $service = new ResumableService($this->provider, $config);

        $identifier = 'test-upload-'.uniqid();
        $filename = 'test-file.txt';

        $I->assertFalse($service->chunkExists($identifier, $filename, 1));

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, 'chunk content');
        rewind($stream);
        $service->saveChunk($identifier, $filename, 1, $stream);
        fclose($stream);

        $I->assertTrue($service->chunkExists($identifier, $filename, 1));

        $I->assertTrue($service->isComplete($identifier, $filename, 1));

        $assembledFilename = $service->assemble($identifier, $filename);
        $I->assertNotEmpty($assembledFilename);
    }

    public function testDeleteTmpFile(DocExamplesTester $I): void
    {
        $config = new ResumableConfig();
        $service = new ResumableService($this->provider, $config);

        $this->provider->write('@bltmp/to-delete.txt', 'content');
        $service->deleteTmpFile('@bltmp/to-delete.txt');
        $I->assertFalse($this->provider->fileExists('@bltmp/to-delete.txt'));
    }

    public function testDeleteTmpFileRejectsNonTmpPath(DocExamplesTester $I): void
    {
        $config = new ResumableConfig();
        $service = new ResumableService($this->provider, $config);

        $I->expectThrowable(\InvalidArgumentException::class, function () use ($service) {
            $service->deleteTmpFile('@blfs/important.txt');
        });
    }

    public function testIsImageAndIsSvg(DocExamplesTester $I): void
    {
        $config = new ResumableConfig();
        $service = new ResumableService($this->provider, $config);

        $I->assertTrue($service->isImage('@bltmp/photo.jpg'));
        $I->assertTrue($service->isImage('@bltmp/photo.png'));
        $I->assertTrue($service->isImage('@bltmp/photo.webp'));
        $I->assertFalse($service->isImage('@bltmp/doc.pdf'));

        $I->assertTrue($service->isSvg('@bltmp/icon.svg'));
        $I->assertFalse($service->isSvg('@bltmp/photo.jpg'));
    }

    public function testGetPreview(DocExamplesTester $I): void
    {
        $config = new ResumableConfig();
        $service = new ResumableService($this->provider, $config);

        $this->provider->write(
            '@bltmp/preview-test.jpg',
            file_get_contents(dirname(__DIR__).'/data/test-image.jpg')
        );

        $preview = $service->getPreview('@bltmp/preview-test.jpg');
        $I->assertNotNull($preview);
        $I->assertArrayHasKey('stream', $preview);
        $I->assertArrayHasKey('mimeType', $preview);
        $I->assertArrayHasKey('filename', $preview);
        $I->assertIsResource($preview['stream']);
        fclose($preview['stream']);
    }

    public function testGetPreviewReturnsNullForMissing(DocExamplesTester $I): void
    {
        $config = new ResumableConfig();
        $service = new ResumableService($this->provider, $config);

        $preview = $service->getPreview('@bltmp/nonexistent.jpg');
        $I->assertNull($preview);
    }
}
