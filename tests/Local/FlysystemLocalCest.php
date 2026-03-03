<?php

declare(strict_types=1);

namespace Blackcube\FileProvider\Tests\Local;

use Blackcube\FileProvider\FlysystemProviderInterface;
use Blackcube\FileProvider\Tests\Support\FlysystemHelper;
use Blackcube\FileProvider\Tests\Support\LocalTester;

class FlysystemLocalCest
{
    private FlysystemProviderInterface $fs;

    public function _before(LocalTester $I): void
    {
        FlysystemHelper::resetTestId();
        $this->fs = FlysystemHelper::createFilesystem();
    }

    public function _after(LocalTester $I): void
    {
        if (FlysystemHelper::isLocal()) {
            $basePath = $_ENV['FILESYSTEM_LOCAL_PATH'] ?? '@data/files';
            $basePath = FlysystemHelper::resolvePath($basePath);
            FlysystemHelper::cleanupLocal($basePath . '/' . FlysystemHelper::getTestId());
        } else {
            FlysystemHelper::cleanupS3($this->fs);
        }
    }

    public function testWriteAndRead(LocalTester $I): void
    {
        $this->fs->write('test.txt', 'Hello World');

        $I->assertTrue($this->fs->fileExists('test.txt'));
        $I->assertEquals('Hello World', $this->fs->read('test.txt'));
    }

    public function testDelete(LocalTester $I): void
    {
        $this->fs->write('to-delete.txt', 'content');
        $I->assertTrue($this->fs->fileExists('to-delete.txt'));

        $this->fs->delete('to-delete.txt');
        $I->assertFalse($this->fs->fileExists('to-delete.txt'));
    }

    public function testCreateDirectoryAndWriteFile(LocalTester $I): void
    {
        // Create a file in a subdirectory (the directory is created implicitly)
        $this->fs->write('subdir/file.txt', 'content');
        $I->assertTrue($this->fs->directoryExists('subdir'));
        $I->assertTrue($this->fs->fileExists('subdir/file.txt'));

        // Delete the file
        $this->fs->delete('subdir/file.txt');
        $I->assertFalse($this->fs->fileExists('subdir/file.txt'));
    }

    public function testCopy(LocalTester $I): void
    {
        $this->fs->write('original.txt', 'original content');
        $this->fs->copy('original.txt', 'copy.txt');

        $I->assertTrue($this->fs->fileExists('original.txt'));
        $I->assertTrue($this->fs->fileExists('copy.txt'));
        $I->assertEquals('original content', $this->fs->read('copy.txt'));
    }

    public function testMove(LocalTester $I): void
    {
        $this->fs->write('source.txt', 'moved content');
        $this->fs->move('source.txt', 'destination.txt');

        $I->assertFalse($this->fs->fileExists('source.txt'));
        $I->assertTrue($this->fs->fileExists('destination.txt'));
        $I->assertEquals('moved content', $this->fs->read('destination.txt'));
    }

    public function testFileSize(LocalTester $I): void
    {
        $content = 'Test content for size';
        $this->fs->write('sized.txt', $content);

        $I->assertEquals(strlen($content), $this->fs->fileSize('sized.txt'));
    }

    public function testMimeType(LocalTester $I): void
    {
        $this->fs->write('text.txt', 'plain text');
        $mimeType = $this->fs->mimeType('text.txt');

        $I->assertStringContainsString('text', $mimeType);
    }

    public function testListContents(LocalTester $I): void
    {
        $this->fs->write('file1.txt', 'content1');
        $this->fs->write('file2.txt', 'content2');
        $this->fs->createDirectory('subdir');
        $this->fs->write('subdir/file3.txt', 'content3');

        $contents = iterator_to_array($this->fs->listContents(''));
        $I->assertCount(3, $contents);

        $recursiveContents = iterator_to_array($this->fs->listContents('', true));
        $I->assertCount(4, $recursiveContents);
    }

    public function testReadStream(LocalTester $I): void
    {
        $this->fs->write('stream.txt', 'stream content');
        $stream = $this->fs->readStream('stream.txt');

        $I->assertTrue(is_resource($stream));
        $I->assertEquals('stream content', stream_get_contents($stream));
        fclose($stream);
    }

    public function testWriteStream(LocalTester $I): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, 'stream write content');
        $stream = fopen($tempFile, 'r');

        $this->fs->writeStream('from-stream.txt', $stream);
        fclose($stream);
        unlink($tempFile);

        $I->assertTrue($this->fs->fileExists('from-stream.txt'));
        $I->assertEquals('stream write content', $this->fs->read('from-stream.txt'));
    }

    public function testHas(LocalTester $I): void
    {
        $this->fs->write('exists.txt', 'content');
        $this->fs->createDirectory('existing-dir');

        $I->assertTrue($this->fs->has('exists.txt'));
        $I->assertTrue($this->fs->has('existing-dir'));
        $I->assertFalse($this->fs->has('does-not-exist.txt'));
    }
}
