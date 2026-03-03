<?php

declare(strict_types=1);

namespace Blackcube\FileProvider\Tests\Provider;

use Blackcube\FileProvider\Exception\UnknownFilesystemException;
use Blackcube\FileProvider\FileProvider;
use Blackcube\FileProvider\FlysystemProviderInterface;
use Blackcube\FileProvider\FlysystemLocal;
use Blackcube\FileProvider\Tests\Support\FlysystemHelper;
use Blackcube\FileProvider\Tests\Support\ManagerTester;

class FileProviderCest
{
    private FlysystemProviderInterface $tmpFs;
    private FlysystemProviderInterface $storageFs;
    private FileProvider $provider;

    public function _before(ManagerTester $I): void
    {
        FlysystemHelper::resetTestId();
        $this->tmpFs = FlysystemHelper::createFilesystem('tmp');
        $this->storageFs = FlysystemHelper::createFilesystem('storage');

        $this->provider = new FileProvider([
            '@bltmp' => $this->tmpFs,
            '@blfs' => $this->storageFs,
        ]);
    }

    public function _after(ManagerTester $I): void
    {
        if (FlysystemHelper::isLocal()) {
            $basePath = $_ENV['FILESYSTEM_LOCAL_PATH'] ?? '@data/files';
            $basePath = FlysystemHelper::resolvePath($basePath);
            FlysystemHelper::cleanupLocal($basePath . '/' . FlysystemHelper::getTestId());
        } else {
            FlysystemHelper::cleanupS3($this->tmpFs);
            FlysystemHelper::cleanupS3($this->storageFs);
        }
    }

    public function testResolvePath(ManagerTester $I): void
    {
        [$alias, $path] = $this->provider->resolvePath('@bltmp/file.txt');
        $I->assertEquals('@bltmp', $alias);
        $I->assertEquals('file.txt', $path);

        [$alias, $path] = $this->provider->resolvePath('@blfs/dir/file.txt');
        $I->assertEquals('@blfs', $alias);
        $I->assertEquals('dir/file.txt', $path);

        // Default alias for paths without prefix
        [$alias, $path] = $this->provider->resolvePath('some/path.txt');
        $I->assertEquals('@blfs', $alias);
        $I->assertEquals('some/path.txt', $path);
    }

    public function testWriteAndReadWithAlias(ManagerTester $I): void
    {
        $this->provider->write('@bltmp/upload.txt', 'tmp content');
        $this->provider->write('@blfs/stored.txt', 'storage content');

        $I->assertTrue($this->provider->fileExists('@bltmp/upload.txt'));
        $I->assertTrue($this->provider->fileExists('@blfs/stored.txt'));
        $I->assertEquals('tmp content', $this->provider->read('@bltmp/upload.txt'));
        $I->assertEquals('storage content', $this->provider->read('@blfs/stored.txt'));
    }

    public function testMoveAcrossFilesystems(ManagerTester $I): void
    {
        $this->provider->write('@bltmp/to-move.txt', 'moving content');
        $I->assertTrue($this->provider->fileExists('@bltmp/to-move.txt'));

        $this->provider->move('@bltmp/to-move.txt', '@blfs/moved.txt');

        $I->assertFalse($this->provider->fileExists('@bltmp/to-move.txt'));
        $I->assertTrue($this->provider->fileExists('@blfs/moved.txt'));
        $I->assertEquals('moving content', $this->provider->read('@blfs/moved.txt'));
    }

    public function testCopyAcrossFilesystems(ManagerTester $I): void
    {
        $this->provider->write('@bltmp/to-copy.txt', 'copy content');

        $this->provider->copy('@bltmp/to-copy.txt', '@blfs/copied.txt');

        $I->assertTrue($this->provider->fileExists('@bltmp/to-copy.txt'));
        $I->assertTrue($this->provider->fileExists('@blfs/copied.txt'));
        $I->assertEquals('copy content', $this->provider->read('@blfs/copied.txt'));
    }

    public function testMoveSameFilesystem(ManagerTester $I): void
    {
        $this->provider->write('@blfs/original.txt', 'content');
        $this->provider->move('@blfs/original.txt', '@blfs/renamed.txt');

        $I->assertFalse($this->provider->fileExists('@blfs/original.txt'));
        $I->assertTrue($this->provider->fileExists('@blfs/renamed.txt'));
    }

    public function testAddFilesystem(ManagerTester $I): void
    {
        $customFs = FlysystemHelper::createFilesystem('custom');
        $this->provider->addFilesystem('@custom', $customFs);

        $I->assertTrue($this->provider->hasFilesystem('@custom'));
        $I->assertContains('@custom', $this->provider->getAliases());
    }

    public function testGetFilesystem(ManagerTester $I): void
    {
        $fs = $this->provider->getFilesystem('@bltmp');
        $I->assertInstanceOf(FlysystemProviderInterface::class, $fs);
    }

    public function testUnknownFilesystemThrowsException(ManagerTester $I): void
    {
        $I->expectThrowable(UnknownFilesystemException::class, function () {
            $this->provider->getFilesystem('@unknown');
        });
    }

    public function testCanHandle(ManagerTester $I): void
    {
        $I->assertTrue($this->provider->canHandle('@bltmp/file.txt'));
        $I->assertTrue($this->provider->canHandle('@blfs/file.txt'));
        $I->assertFalse($this->provider->canHandle('default/path.txt')); // No known prefix
    }

    public function testCanHandleWithUnknownPrefix(ManagerTester $I): void
    {
        // Create a provider with only one filesystem
        $provider = new FileProvider([
            '@blfs' => $this->storageFs,
        ], '@blfs');

        $I->assertTrue($provider->canHandle('@blfs/image.jpg'));
        $I->assertFalse($provider->canHandle('@unknown/file.jpg'));
    }

    public function testDeleteWithAlias(ManagerTester $I): void
    {
        $this->provider->write('@bltmp/to-delete.txt', 'content');
        $I->assertTrue($this->provider->fileExists('@bltmp/to-delete.txt'));

        $this->provider->delete('@bltmp/to-delete.txt');
        $I->assertFalse($this->provider->fileExists('@bltmp/to-delete.txt'));
    }

    public function testCreateDirectoryAndWriteFileWithAlias(ManagerTester $I): void
    {
        // Create a file in a subdirectory (the directory is created implicitly)
        $this->provider->write('@blfs/new-dir/file.txt', 'content');
        $I->assertTrue($this->provider->directoryExists('@blfs/new-dir'));
        $I->assertTrue($this->provider->fileExists('@blfs/new-dir/file.txt'));

        // Delete the file
        $this->provider->delete('@blfs/new-dir/file.txt');
        $I->assertFalse($this->provider->fileExists('@blfs/new-dir/file.txt'));
    }

    public function testMetadataWithAlias(ManagerTester $I): void
    {
        $content = 'Test content';
        $this->provider->write('@blfs/meta.txt', $content);

        $I->assertEquals(strlen($content), $this->provider->fileSize('@blfs/meta.txt'));
        $I->assertIsInt($this->provider->lastModified('@blfs/meta.txt'));
        $I->assertStringContainsString('text', $this->provider->mimeType('@blfs/meta.txt'));
    }

    public function testListContentsWithAlias(ManagerTester $I): void
    {
        $this->provider->write('@blfs/file1.txt', 'content1');
        $this->provider->write('@blfs/file2.txt', 'content2');

        $contents = iterator_to_array($this->provider->listContents('@blfs'));
        $I->assertCount(2, $contents);
    }

    public function testDefaultAliasUsedForNonPrefixedPaths(ManagerTester $I): void
    {
        $this->provider->write('no-prefix.txt', 'default content');

        // Should be written to @blfs (default)
        $I->assertTrue($this->provider->fileExists('@blfs/no-prefix.txt'));
        $I->assertTrue($this->provider->fileExists('no-prefix.txt'));
        $I->assertEquals('default content', $this->provider->read('no-prefix.txt'));
    }

    public function testHasWithAlias(ManagerTester $I): void
    {
        // Test has() on non-existent file
        $I->assertFalse($this->provider->has('@bltmp/nonexistent.txt'));
        $I->assertFalse($this->provider->has('@blfs/nonexistent.txt'));

        // Write files and test has()
        $this->provider->write('@bltmp/has-test.txt', 'tmp content');
        $this->provider->write('@blfs/has-test.txt', 'storage content');

        $I->assertTrue($this->provider->has('@bltmp/has-test.txt'));
        $I->assertTrue($this->provider->has('@blfs/has-test.txt'));

        // Test has() on directory
        $this->provider->createDirectory('@blfs/has-dir');
        $I->assertTrue($this->provider->has('@blfs/has-dir'));
    }

    public function testReadStreamWithAlias(ManagerTester $I): void
    {
        $content = 'Stream content for testing';
        $this->provider->write('@bltmp/stream-read.txt', $content);
        $this->provider->write('@blfs/stream-read.txt', $content);

        // Test readStream on @bltmp
        $stream = $this->provider->readStream('@bltmp/stream-read.txt');
        $I->assertIsResource($stream);
        $I->assertEquals($content, stream_get_contents($stream));
        fclose($stream);

        // Test readStream on @blfs
        $stream = $this->provider->readStream('@blfs/stream-read.txt');
        $I->assertIsResource($stream);
        $I->assertEquals($content, stream_get_contents($stream));
        fclose($stream);
    }

    public function testWriteStreamWithAlias(ManagerTester $I): void
    {
        $content = 'Stream write content';

        // Create stream from string
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        // Write stream to @bltmp
        $this->provider->writeStream('@bltmp/stream-write.txt', $stream);
        fclose($stream);

        $I->assertTrue($this->provider->fileExists('@bltmp/stream-write.txt'));
        $I->assertEquals($content, $this->provider->read('@bltmp/stream-write.txt'));

        // Create another stream for @blfs
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $this->provider->writeStream('@blfs/stream-write.txt', $stream);
        fclose($stream);

        $I->assertTrue($this->provider->fileExists('@blfs/stream-write.txt'));
        $I->assertEquals($content, $this->provider->read('@blfs/stream-write.txt'));
    }

    public function testDeleteDirectoryWithAlias(ManagerTester $I): void
    {
        // Create directory with files
        $this->provider->write('@bltmp/delete-dir/file1.txt', 'content1');
        $this->provider->write('@bltmp/delete-dir/file2.txt', 'content2');
        $this->provider->write('@blfs/delete-dir/file1.txt', 'content1');

        $I->assertTrue($this->provider->directoryExists('@bltmp/delete-dir'));
        $I->assertTrue($this->provider->directoryExists('@blfs/delete-dir'));

        // Delete directories
        $this->provider->deleteDirectory('@bltmp/delete-dir');
        $this->provider->deleteDirectory('@blfs/delete-dir');

        $I->assertFalse($this->provider->directoryExists('@bltmp/delete-dir'));
        $I->assertFalse($this->provider->directoryExists('@blfs/delete-dir'));
    }

    public function testCreateDirectoryWithAlias(ManagerTester $I): void
    {
        // Create directories explicitly
        $this->provider->createDirectory('@bltmp/explicit-dir');
        $this->provider->createDirectory('@blfs/explicit-dir');
        $this->provider->createDirectory('@blfs/nested/deep/dir');

        $I->assertTrue($this->provider->directoryExists('@bltmp/explicit-dir'));
        $I->assertTrue($this->provider->directoryExists('@blfs/explicit-dir'));
        $I->assertTrue($this->provider->directoryExists('@blfs/nested/deep/dir'));
        $I->assertTrue($this->provider->directoryExists('@blfs/nested/deep'));
        $I->assertTrue($this->provider->directoryExists('@blfs/nested'));
    }

    public function testVisibilityWithAlias(ManagerTester $I): void
    {
        $this->provider->write('@bltmp/visibility-test.txt', 'content');
        $this->provider->write('@blfs/visibility-test.txt', 'content');

        // Get default visibility
        $tmpVisibility = $this->provider->visibility('@bltmp/visibility-test.txt');
        $fsVisibility = $this->provider->visibility('@blfs/visibility-test.txt');

        $I->assertContains($tmpVisibility, ['public', 'private']);
        $I->assertContains($fsVisibility, ['public', 'private']);
    }

    public function testSetVisibilityWithAlias(ManagerTester $I): void
    {
        // Note: setVisibility does not work on MinIO (no ACL support)
        // This test only runs on local filesystem
        if (!FlysystemHelper::isLocal()) {
            $I->markTestSkipped('setVisibility is not supported on S3/MinIO');
            return;
        }

        $this->provider->write('@bltmp/set-visibility.txt', 'content');
        $this->provider->write('@blfs/set-visibility.txt', 'content');

        // Set visibility to private
        $this->provider->setVisibility('@bltmp/set-visibility.txt', 'private');
        $this->provider->setVisibility('@blfs/set-visibility.txt', 'private');

        $I->assertEquals('private', $this->provider->visibility('@bltmp/set-visibility.txt'));
        $I->assertEquals('private', $this->provider->visibility('@blfs/set-visibility.txt'));

        // Set visibility to public
        $this->provider->setVisibility('@bltmp/set-visibility.txt', 'public');
        $this->provider->setVisibility('@blfs/set-visibility.txt', 'public');

        $I->assertEquals('public', $this->provider->visibility('@bltmp/set-visibility.txt'));
        $I->assertEquals('public', $this->provider->visibility('@blfs/set-visibility.txt'));
    }

    public function testStreamCrossFilesystem(ManagerTester $I): void
    {
        $content = 'Cross filesystem stream content';

        // Write to @bltmp using stream
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);
        $this->provider->writeStream('@bltmp/cross-stream.txt', $stream);
        fclose($stream);

        // Read from @bltmp as stream and write to @blfs
        $readStream = $this->provider->readStream('@bltmp/cross-stream.txt');
        $this->provider->writeStream('@blfs/cross-stream.txt', $readStream);
        if (is_resource($readStream)) {
            fclose($readStream);
        }

        // Verify content integrity
        $I->assertTrue($this->provider->fileExists('@blfs/cross-stream.txt'));
        $I->assertEquals($content, $this->provider->read('@blfs/cross-stream.txt'));
        $I->assertEquals(
            $this->provider->read('@bltmp/cross-stream.txt'),
            $this->provider->read('@blfs/cross-stream.txt')
        );
    }
}
