<?php

declare(strict_types=1);

namespace Blackcube\FileProvider\Tests\Integration;

use Blackcube\FileProvider\FlysystemAwsS3;
use Blackcube\FileProvider\FlysystemLocal;
use Blackcube\FileProvider\Tests\Support\FlysystemHelper;
use Blackcube\FileProvider\Tests\Support\IntegrationTester;

/**
 * Cross-filesystem integrity tests (local + S3)
 */
class CrossFilesystemCest
{
    private FlysystemLocal $localFs;
    private FlysystemAwsS3 $s3Fs;
    private string $testId;

    public function _before(IntegrationTester $I): void
    {
        FlysystemHelper::resetTestId();
        $this->testId = FlysystemHelper::getTestId();

        // Create both filesystems
        $this->localFs = FlysystemHelper::createLocalFilesystem();
        $this->s3Fs = FlysystemHelper::createS3Filesystem();
    }

    public function _after(IntegrationTester $I): void
    {
        // Cleanup local
        $basePath = $_ENV['FILESYSTEM_LOCAL_PATH'] ?? '@data/files';
        $basePath = FlysystemHelper::resolvePath($basePath);
        FlysystemHelper::cleanupLocal($basePath . '/' . $this->testId);

        // Cleanup S3
        FlysystemHelper::cleanupS3($this->s3Fs);
    }

    public function testFileIntegrityAcrossFilesystems(IntegrationTester $I): void
    {
        // Original content with varied data
        $originalContent = "Hello World!\nCeci est un test d'intégrité.\n"
            . "Caractères spéciaux: éàü@#$%^&*()\n"
            . bin2hex(random_bytes(32)); // Encoded binary data

        $originalSha256 = hash('sha256', $originalContent);

        // 1. Upload to S3
        $this->s3Fs->write('a.txt', $originalContent);

        // 2. Verify existence on S3
        $I->assertTrue($this->s3Fs->fileExists('a.txt'), 'File must exist on S3');

        // 3. Upload to local
        $this->localFs->write('a.txt', $originalContent);

        // 4. Verify existence on local
        $I->assertTrue($this->localFs->fileExists('a.txt'), 'File must exist locally');

        // 5. Retrieve and SHA256 of local file
        $localContent = $this->localFs->read('a.txt');
        $localSha256 = hash('sha256', $localContent);

        // 6. Retrieve and SHA256 of S3 file
        $s3Content = $this->s3Fs->read('a.txt');
        $s3Sha256 = hash('sha256', $s3Content);

        // 7. Compare SHA256 hashes
        $I->assertEquals($originalSha256, $localSha256, 'Local SHA256 must match original');
        $I->assertEquals($originalSha256, $s3Sha256, 'S3 SHA256 must match original');
        $I->assertEquals($localSha256, $s3Sha256, 'Local and S3 SHA256 must be identical');
    }

    public function testBinaryFileIntegrity(IntegrationTester $I): void
    {
        // Binary file (simulated)
        $originalContent = random_bytes(1024);
        $originalSha256 = hash('sha256', $originalContent);

        // Upload to both filesystems
        $this->s3Fs->write('binary.bin', $originalContent);
        $this->localFs->write('binary.bin', $originalContent);

        // Verify existence
        $I->assertTrue($this->s3Fs->fileExists('binary.bin'));
        $I->assertTrue($this->localFs->fileExists('binary.bin'));

        // Retrieve and verify SHA256
        $localSha256 = hash('sha256', $this->localFs->read('binary.bin'));
        $s3Sha256 = hash('sha256', $this->s3Fs->read('binary.bin'));

        $I->assertEquals($originalSha256, $localSha256);
        $I->assertEquals($originalSha256, $s3Sha256);
    }

    public function testStreamIntegrity(IntegrationTester $I): void
    {
        // Original content
        $originalContent = str_repeat("Test stream integrity\n", 100);
        $originalSha256 = hash('sha256', $originalContent);

        // Write via stream to S3
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, $originalContent);
        $stream = fopen($tempFile, 'r');
        $this->s3Fs->writeStream('stream.txt', $stream);
        fclose($stream);

        // Write via stream to local
        $stream = fopen($tempFile, 'r');
        $this->localFs->writeStream('stream.txt', $stream);
        fclose($stream);
        unlink($tempFile);

        // Read via stream from S3
        $s3Stream = $this->s3Fs->readStream('stream.txt');
        $s3Content = stream_get_contents($s3Stream);
        fclose($s3Stream);
        $s3Sha256 = hash('sha256', $s3Content);

        // Read via stream from local
        $localStream = $this->localFs->readStream('stream.txt');
        $localContent = stream_get_contents($localStream);
        fclose($localStream);
        $localSha256 = hash('sha256', $localContent);

        // Verification
        $I->assertEquals($originalSha256, $s3Sha256);
        $I->assertEquals($originalSha256, $localSha256);
    }

    public function testCrossFilesystemCopyIntegrity(IntegrationTester $I): void
    {
        // Original content
        $originalContent = "Cross-filesystem copy test\n" . bin2hex(random_bytes(64));
        $originalSha256 = hash('sha256', $originalContent);

        // Write to S3
        $this->s3Fs->write('source.txt', $originalContent);

        // Copy from S3 to local via stream
        $stream = $this->s3Fs->readStream('source.txt');
        $this->localFs->writeStream('copied.txt', $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        // Verify integrity
        $copiedContent = $this->localFs->read('copied.txt');
        $copiedSha256 = hash('sha256', $copiedContent);

        $I->assertEquals($originalSha256, $copiedSha256, 'Copied file must have the same SHA256');
    }
}
