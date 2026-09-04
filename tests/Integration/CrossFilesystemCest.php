<?php

declare(strict_types=1);

/**
 * CrossFilesystemCest.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\FileProvider\Tests\Integration;

use Blackcube\FileProvider\Flysystem\FlysystemAwsS3;
use Blackcube\FileProvider\Flysystem\FlysystemLocal;
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

        $this->localFs = FlysystemHelper::createLocalFilesystem();
        $this->s3Fs = FlysystemHelper::createS3Filesystem();
    }

    public function _after(IntegrationTester $I): void
    {
        $basePath = $_ENV['FILESYSTEM_LOCAL_PATH'] ?? '@data/files';
        $basePath = FlysystemHelper::resolvePath($basePath);
        FlysystemHelper::cleanupLocal($basePath.'/'.$this->testId);

        FlysystemHelper::cleanupS3($this->s3Fs);
    }

    public function testFileIntegrityAcrossFilesystems(IntegrationTester $I): void
    {
        $originalContent = "Hello World!\nThis is an integrity test.\n"
            ."Special characters: éàü@#$%^&*()\n"
            .bin2hex(random_bytes(32));

        $originalSha256 = hash('sha256', $originalContent);

        $this->s3Fs->write('a.txt', $originalContent);

        $I->assertTrue($this->s3Fs->fileExists('a.txt'), 'File must exist on S3');

        $this->localFs->write('a.txt', $originalContent);

        $I->assertTrue($this->localFs->fileExists('a.txt'), 'File must exist locally');

        $localContent = $this->localFs->read('a.txt');
        $localSha256 = hash('sha256', $localContent);

        $s3Content = $this->s3Fs->read('a.txt');
        $s3Sha256 = hash('sha256', $s3Content);

        $I->assertEquals($originalSha256, $localSha256, 'Local SHA256 must match original');
        $I->assertEquals($originalSha256, $s3Sha256, 'S3 SHA256 must match original');
        $I->assertEquals($localSha256, $s3Sha256, 'Local and S3 SHA256 must be identical');
    }

    public function testBinaryFileIntegrity(IntegrationTester $I): void
    {
        $originalContent = random_bytes(1024);
        $originalSha256 = hash('sha256', $originalContent);

        $this->s3Fs->write('binary.bin', $originalContent);
        $this->localFs->write('binary.bin', $originalContent);

        $I->assertTrue($this->s3Fs->fileExists('binary.bin'));
        $I->assertTrue($this->localFs->fileExists('binary.bin'));

        $localSha256 = hash('sha256', $this->localFs->read('binary.bin'));
        $s3Sha256 = hash('sha256', $this->s3Fs->read('binary.bin'));

        $I->assertEquals($originalSha256, $localSha256);
        $I->assertEquals($originalSha256, $s3Sha256);
    }

    public function testStreamIntegrity(IntegrationTester $I): void
    {
        $originalContent = str_repeat("Test stream integrity\n", 100);
        $originalSha256 = hash('sha256', $originalContent);

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, $originalContent);
        $stream = fopen($tempFile, 'r');
        $this->s3Fs->writeStream('stream.txt', $stream);
        fclose($stream);

        $stream = fopen($tempFile, 'r');
        $this->localFs->writeStream('stream.txt', $stream);
        fclose($stream);
        unlink($tempFile);

        $s3Stream = $this->s3Fs->readStream('stream.txt');
        $s3Content = stream_get_contents($s3Stream);
        fclose($s3Stream);
        $s3Sha256 = hash('sha256', $s3Content);

        $localStream = $this->localFs->readStream('stream.txt');
        $localContent = stream_get_contents($localStream);
        fclose($localStream);
        $localSha256 = hash('sha256', $localContent);

        $I->assertEquals($originalSha256, $s3Sha256);
        $I->assertEquals($originalSha256, $localSha256);
    }

    public function testCrossFilesystemCopyIntegrity(IntegrationTester $I): void
    {
        $originalContent = "Cross-filesystem copy test\n".bin2hex(random_bytes(64));
        $originalSha256 = hash('sha256', $originalContent);

        $this->s3Fs->write('source.txt', $originalContent);

        $stream = $this->s3Fs->readStream('source.txt');
        $this->localFs->writeStream('copied.txt', $stream);
        if (is_resource($stream) === true) {
            fclose($stream);
        }

        $copiedContent = $this->localFs->read('copied.txt');
        $copiedSha256 = hash('sha256', $copiedContent);

        $I->assertEquals($originalSha256, $copiedSha256, 'Copied file must have the same SHA256');
    }
}
