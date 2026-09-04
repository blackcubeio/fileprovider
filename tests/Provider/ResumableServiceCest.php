<?php

declare(strict_types=1);

/**
 * ResumableServiceCest.php
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
use Blackcube\FileProvider\Resumable\ResumableConfig;
use Blackcube\FileProvider\Resumable\ResumableService;
use Blackcube\FileProvider\Tests\Support\FlysystemHelper;
use Blackcube\FileProvider\Tests\Support\ManagerTester;
use Yiisoft\Aliases\Aliases;

/**
 * Tests for ResumableService against a real filesystem
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class ResumableServiceCest
{
    private FlysystemProviderInterface $tmpFs;
    private FileProvider $provider;
    private ResumableService $service;

    public function _before(ManagerTester $I): void
    {
        FlysystemHelper::resetTestId();
        $this->tmpFs = FlysystemHelper::createFilesystem('bltmp');

        $this->provider = new FileProvider(new Aliases());
        $this->provider->addFilesystem('@bltmp', $this->tmpFs);

        $this->service = new ResumableService($this->provider, new ResumableConfig());
    }

    public function _after(ManagerTester $I): void
    {
        FlysystemHelper::cleanup($this->tmpFs);
    }

    public function testAssembleJoinsChunksInOrder(ManagerTester $I): void
    {
        $identifier = 'ordered-identifier';

        $this->writeChunk($identifier, 'ordered.txt', 1, 'FIRST-');
        $this->writeChunk($identifier, 'ordered.txt', 2, 'SECOND-');
        $this->writeChunk($identifier, 'ordered.txt', 10, 'TENTH');

        $finalFilename = $this->service->assemble($identifier, 'ordered.txt');

        $I->assertSame('ordered.txt', $finalFilename);
        $I->assertSame('FIRST-SECOND-TENTH', $this->provider->read('@bltmp/ordered.txt'));
    }

    public function testAssembleIgnoresChunksOfAnotherFile(ManagerTester $I): void
    {
        $identifier = 'shared-identifier';

        $this->writeChunk($identifier, 'wanted.txt', 1, 'WANTED-1');
        $this->writeChunk($identifier, 'wanted.txt', 2, 'WANTED-2');
        $this->writeChunk($identifier, 'other.txt', 1, 'OTHER-1');

        $finalFilename = $this->service->assemble($identifier, 'wanted.txt');

        $I->assertSame('wanted.txt', $finalFilename);
        $I->assertSame('WANTED-1WANTED-2', $this->provider->read('@bltmp/wanted.txt'));
    }

    public function testFileExistsAnswersOnHandledPaths(ManagerTester $I): void
    {
        $this->provider->write('@bltmp/present.txt', 'here');

        $I->assertTrue($this->service->fileExists('@bltmp/present.txt'));
        $I->assertFalse($this->service->fileExists('@bltmp/missing.txt'));
        $I->assertFalse($this->service->fileExists('@unknown/present.txt'));
    }

    private function writeChunk(string $identifier, string $filename, int $chunkNumber, string $contents): void
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);

        $this->service->saveChunk($identifier, $filename, $chunkNumber, $stream);

        if (is_resource($stream) === true) {
            fclose($stream);
        }
    }
}
