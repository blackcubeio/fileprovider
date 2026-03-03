<?php

declare(strict_types=1);

namespace Blackcube\FileProvider\Tests\Integration;

use Blackcube\FileProvider\FlysystemAwsS3;
use Blackcube\FileProvider\FlysystemProviderInterface;
use Blackcube\FileProvider\FlysystemLocal;
use Blackcube\FileProvider\Tests\Support\FlysystemHelper;
use Blackcube\FileProvider\Tests\Support\IntegrationTester;

/**
 * Methods tests on both filesystems
 */
class MethodsCest
{
    private FlysystemLocal $localFs;
    private FlysystemAwsS3 $s3Fs;
    private string $testId;

    public function _before(IntegrationTester $I): void
    {
        FlysystemHelper::resetTestId();
        $this->testId = FlysystemHelper::getTestId();
        $this->localFs = FlysystemHelper::createLocalFilesystem();
        $this->s3Fs = FlysystemHelper::createS3Filesystem();
    }

    public function _after(IntegrationTester $I): void
    {
        $basePath = $_ENV['FILESYSTEM_LOCAL_PATH'] ?? '@data/files';
        $basePath = FlysystemHelper::resolvePath($basePath);
        FlysystemHelper::cleanupLocal($basePath . '/' . $this->testId);
        FlysystemHelper::cleanupS3($this->s3Fs);
    }

    // ========== readStream ==========

    public function testReadStreamLocal(IntegrationTester $I): void
    {
        $content = 'Test readStream local content';
        $this->localFs->write('readstream.txt', $content);

        $stream = $this->localFs->readStream('readstream.txt');
        $I->assertTrue(is_resource($stream), 'readStream must return a resource');

        $readContent = stream_get_contents($stream);
        fclose($stream);

        $I->assertEquals($content, $readContent, 'Content read via stream must match');
    }

    public function testReadStreamS3(IntegrationTester $I): void
    {
        $content = 'Test readStream S3 content';
        $this->s3Fs->write('readstream.txt', $content);

        $stream = $this->s3Fs->readStream('readstream.txt');
        $I->assertTrue(is_resource($stream), 'readStream must return a resource');

        $readContent = stream_get_contents($stream);
        fclose($stream);

        $I->assertEquals($content, $readContent, 'Content read via stream must match');
    }

    // ========== writeStream ==========

    public function testWriteStreamLocal(IntegrationTester $I): void
    {
        $content = 'Test writeStream local content';

        // Create a temporary stream
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $this->localFs->writeStream('writestream.txt', $stream);
        fclose($stream);

        $I->assertTrue($this->localFs->fileExists('writestream.txt'));
        $I->assertEquals($content, $this->localFs->read('writestream.txt'));
    }

    public function testWriteStreamS3(IntegrationTester $I): void
    {
        $content = 'Test writeStream S3 content';

        // Create a temporary stream
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $this->s3Fs->writeStream('writestream.txt', $stream);
        fclose($stream);

        $I->assertTrue($this->s3Fs->fileExists('writestream.txt'));
        $I->assertEquals($content, $this->s3Fs->read('writestream.txt'));
    }

    // ========== has ==========

    public function testHasFileLocal(IntegrationTester $I): void
    {
        $I->assertFalse($this->localFs->has('hastest.txt'), 'File must not exist before creation');

        $this->localFs->write('hastest.txt', 'content');
        $I->assertTrue($this->localFs->has('hastest.txt'), 'File must exist after creation');

        $this->localFs->delete('hastest.txt');
        $I->assertFalse($this->localFs->has('hastest.txt'), 'File must no longer exist after deletion');
    }

    public function testHasFileS3(IntegrationTester $I): void
    {
        $I->assertFalse($this->s3Fs->has('hastest.txt'), 'File must not exist before creation');

        $this->s3Fs->write('hastest.txt', 'content');
        $I->assertTrue($this->s3Fs->has('hastest.txt'), 'File must exist after creation');

        $this->s3Fs->delete('hastest.txt');
        $I->assertFalse($this->s3Fs->has('hastest.txt'), 'File must no longer exist after deletion');
    }

    public function testHasDirectoryLocal(IntegrationTester $I): void
    {
        $this->localFs->write('subdir/file.txt', 'content');
        $I->assertTrue($this->localFs->has('subdir'), 'Directory must exist');
        $I->assertTrue($this->localFs->has('subdir/file.txt'), 'File in directory must exist');
    }

    public function testHasDirectoryS3(IntegrationTester $I): void
    {
        $this->s3Fs->write('subdir/file.txt', 'content');
        $I->assertTrue($this->s3Fs->has('subdir'), 'Directory must exist');
        $I->assertTrue($this->s3Fs->has('subdir/file.txt'), 'File in directory must exist');
    }

    // ========== createDirectory ==========

    public function testCreateDirectoryLocal(IntegrationTester $I): void
    {
        $this->localFs->createDirectory('newdir');
        $I->assertTrue($this->localFs->directoryExists('newdir'), 'Directory must exist after creation');

        // Write a file inside
        $this->localFs->write('newdir/file.txt', 'content');
        $I->assertTrue($this->localFs->fileExists('newdir/file.txt'));
    }

    public function testCreateDirectoryS3(IntegrationTester $I): void
    {
        $this->s3Fs->createDirectory('newdir');
        // On S3, createDirectory creates a placeholder
        // We verify by writing a file inside
        $this->s3Fs->write('newdir/file.txt', 'content');
        $I->assertTrue($this->s3Fs->fileExists('newdir/file.txt'));
        $I->assertTrue($this->s3Fs->directoryExists('newdir'));
    }

    public function testCreateNestedDirectoryLocal(IntegrationTester $I): void
    {
        $this->localFs->createDirectory('level1/level2/level3');
        $I->assertTrue($this->localFs->directoryExists('level1/level2/level3'));

        $this->localFs->write('level1/level2/level3/deep.txt', 'deep content');
        $I->assertTrue($this->localFs->fileExists('level1/level2/level3/deep.txt'));
    }

    public function testCreateNestedDirectoryS3(IntegrationTester $I): void
    {
        // On S3, we can write directly to a deep path
        $this->s3Fs->write('level1/level2/level3/deep.txt', 'deep content');
        $I->assertTrue($this->s3Fs->fileExists('level1/level2/level3/deep.txt'));
        $I->assertTrue($this->s3Fs->directoryExists('level1'));
        $I->assertTrue($this->s3Fs->directoryExists('level1/level2'));
        $I->assertTrue($this->s3Fs->directoryExists('level1/level2/level3'));
    }

    // ========== copy ==========

    public function testCopyLocal(IntegrationTester $I): void
    {
        $content = 'Original content for copy';
        $this->localFs->write('original.txt', $content);

        $this->localFs->copy('original.txt', 'copied.txt');

        $I->assertTrue($this->localFs->fileExists('original.txt'), 'Original must still exist');
        $I->assertTrue($this->localFs->fileExists('copied.txt'), 'Copy must exist');
        $I->assertEquals($content, $this->localFs->read('copied.txt'), 'Content must be identical');
    }

    public function testCopyS3(IntegrationTester $I): void
    {
        $content = 'Original content for copy on S3';
        $this->s3Fs->write('original.txt', $content);

        $this->s3Fs->copy('original.txt', 'copied.txt');

        $I->assertTrue($this->s3Fs->fileExists('original.txt'), 'Original must still exist');
        $I->assertTrue($this->s3Fs->fileExists('copied.txt'), 'Copy must exist');
        $I->assertEquals($content, $this->s3Fs->read('copied.txt'), 'Content must be identical');
    }

    public function testCopyToSubdirectoryLocal(IntegrationTester $I): void
    {
        $content = 'Copy to subdir';
        $this->localFs->write('source.txt', $content);
        $this->localFs->createDirectory('target');

        $this->localFs->copy('source.txt', 'target/destination.txt');

        $I->assertTrue($this->localFs->fileExists('target/destination.txt'));
        $I->assertEquals($content, $this->localFs->read('target/destination.txt'));
    }

    public function testCopyToSubdirectoryS3(IntegrationTester $I): void
    {
        $content = 'Copy to subdir on S3';
        $this->s3Fs->write('source.txt', $content);

        $this->s3Fs->copy('source.txt', 'target/destination.txt');

        $I->assertTrue($this->s3Fs->fileExists('target/destination.txt'));
        $I->assertEquals($content, $this->s3Fs->read('target/destination.txt'));
    }

    public function testCopyPreservesContent(IntegrationTester $I): void
    {
        // Test with binary data
        $content = random_bytes(512);
        $originalSha = hash('sha256', $content);

        $this->localFs->write('binary.bin', $content);
        $this->localFs->copy('binary.bin', 'binary-copy.bin');

        $copiedSha = hash('sha256', $this->localFs->read('binary-copy.bin'));
        $I->assertEquals($originalSha, $copiedSha, 'SHA256 must be identical after copy');

        $this->s3Fs->write('binary.bin', $content);
        $this->s3Fs->copy('binary.bin', 'binary-copy.bin');

        $s3CopiedSha = hash('sha256', $this->s3Fs->read('binary-copy.bin'));
        $I->assertEquals($originalSha, $s3CopiedSha, 'SHA256 S3 must be identical after copy');
    }

    // ========== visibility ==========

    public function testVisibilityLocal(IntegrationTester $I): void
    {
        $this->localFs->write('visible.txt', 'content');

        // Read default visibility
        $visibility = $this->localFs->visibility('visible.txt');
        $I->assertNotEmpty($visibility, 'Visibility must be set');

        // Change visibility
        $this->localFs->setVisibility('visible.txt', 'private');
        $I->assertEquals('private', $this->localFs->visibility('visible.txt'));

        $this->localFs->setVisibility('visible.txt', 'public');
        $I->assertEquals('public', $this->localFs->visibility('visible.txt'));
    }

    public function testVisibilityS3(IntegrationTester $I): void
    {
        $this->s3Fs->write('visible.txt', 'content');

        // Read default visibility
        $visibility = $this->s3Fs->visibility('visible.txt');
        $I->assertNotEmpty($visibility, 'Visibility must be set');

        // Note: setVisibility does not work on MinIO (no ACL support)
        // This test only checks visibility reading
        // On real AWS S3, we could also test setVisibility
    }

    // ========== Combined Local + S3 tests ==========

    public function testStreamCrossFilesystem(IntegrationTester $I): void
    {
        $content = 'Cross filesystem stream test ' . bin2hex(random_bytes(16));
        $originalSha = hash('sha256', $content);

        // Write on local
        $this->localFs->write('source.txt', $content);

        // Read stream from local, write stream to S3
        $stream = $this->localFs->readStream('source.txt');
        $this->s3Fs->writeStream('from-local.txt', $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        // Verify integrity
        $s3Sha = hash('sha256', $this->s3Fs->read('from-local.txt'));
        $I->assertEquals($originalSha, $s3Sha, 'SHA256 must match after Local→S3 transfer');

        // Inverse: S3 to Local
        $stream = $this->s3Fs->readStream('from-local.txt');
        $this->localFs->writeStream('from-s3.txt', $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        $localSha = hash('sha256', $this->localFs->read('from-s3.txt'));
        $I->assertEquals($originalSha, $localSha, 'SHA256 must match after S3→Local transfer');
    }
}
