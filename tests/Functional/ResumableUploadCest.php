<?php

declare(strict_types=1);

/**
 * ResumableUploadCest.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\FileProvider\Tests\Functional;

use Blackcube\FileProvider\Tests\Support\FunctionalTester;
use Codeception\Util\HttpCode;

/**
 * Functional tests for ResumableUploadHandler
 *
 * Tests follow Resumable.js protocol:
 * - POST: multipart/form-data with resumable* params and file chunk
 * - GET: check if chunk exists (for resume) with resumable* params in query string
 */
class ResumableUploadCest
{
    private string $endpoint = '/fileprovider/upload';

    /**
     * Upload small file (single chunk)
     */
    public function uploadSingleChunk(FunctionalTester $I): void
    {
        $I->wantTo('upload a small file in one chunk');

        $testFile = $I->createTestFile('small.txt', 1024);
        $identifier = uniqid('test-');

        $I->sendPost($this->endpoint, [
            'resumableIdentifier' => $identifier,
            'resumableFilename' => 'small.txt',
            'resumableChunkNumber' => '1',
            'resumableChunkSize' => '524288',
            'resumableTotalSize' => '1024',
            'resumableTotalChunks' => '1',
        ], ['file' => $testFile]);

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['complete' => true]);
        $I->seeResponseMatchesJsonType([
            'complete' => 'boolean',
            'finalFilename' => 'string',
        ]);
    }

    /**
     * Upload large file (multiple chunks)
     */
    public function uploadMultipleChunks(FunctionalTester $I): void
    {
        $I->wantTo('upload a large file in multiple chunks');

        $chunkSize = 512;
        $totalSize = 1500;
        $testFile = $I->createTestFile('large.bin', $totalSize);
        $identifier = uniqid('multi-');

        $chunks = $I->splitFileIntoChunks($testFile, $chunkSize);
        $numChunks = count($chunks);

        for ($i = 1; $i <= $numChunks; $i++) {
            $I->sendPost($this->endpoint, [
                'resumableIdentifier' => $identifier,
                'resumableFilename' => 'large.bin',
                'resumableChunkNumber' => (string) $i,
                'resumableChunkSize' => (string) $chunkSize,
                'resumableTotalSize' => (string) $totalSize,
                'resumableTotalChunks' => (string) $numChunks,
            ], ['file' => $chunks[$i]]);

            $I->seeResponseCodeIs(HttpCode::OK);
            $I->seeResponseIsJson();

            if ($i < $numChunks) {
                $I->seeResponseContainsJson(['complete' => false]);
            } else {
                $I->seeResponseContainsJson(['complete' => true]);
            }
        }
    }

    /**
     * Test chunk existence check (GET for resume)
     * Tests with an incomplete multi-chunk upload to verify chunk detection
     */
    public function testChunkExists(FunctionalTester $I): void
    {
        $I->wantTo('check if chunk already exists for resume');

        $identifier = uniqid('resume-');
        $chunkSize = 400;
        $totalSize = 1000;

        $I->sendGet($this->endpoint, [
            'resumableIdentifier' => $identifier,
            'resumableFilename' => 'test.txt',
            'resumableChunkNumber' => '1',
        ]);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);

        $testFile = $I->createTestFile('resume_test.bin', $totalSize);
        $chunks = $I->splitFileIntoChunks($testFile, $chunkSize);

        $I->sendPost($this->endpoint, [
            'resumableIdentifier' => $identifier,
            'resumableFilename' => 'test.txt',
            'resumableChunkNumber' => '1',
            'resumableChunkSize' => (string) $chunkSize,
            'resumableTotalSize' => (string) $totalSize,
            'resumableTotalChunks' => (string) count($chunks),
        ], ['file' => $chunks[1]]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['complete' => false]);

        $I->sendGet($this->endpoint, [
            'resumableIdentifier' => $identifier,
            'resumableFilename' => 'test.txt',
            'resumableChunkNumber' => '1',
        ]);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendGet($this->endpoint, [
            'resumableIdentifier' => $identifier,
            'resumableFilename' => 'test.txt',
            'resumableChunkNumber' => '2',
        ]);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
    }

    /**
     * Test missing parameters return 400
     */
    public function uploadMissingParams(FunctionalTester $I): void
    {
        $I->wantTo('receive 400 when params are missing');

        $I->sendGet($this->endpoint);
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    /**
     * Test filename sanitization (path traversal in filename)
     */
    public function uploadSanitizesFilename(FunctionalTester $I): void
    {
        $I->wantTo('see filename is sanitized');

        $testFile = $I->createTestFile('sanitize_test.txt', 100);
        $identifier = uniqid('sanitize-');

        $I->sendPost($this->endpoint, [
            'resumableIdentifier' => $identifier,
            'resumableFilename' => '../../../etc/passwd',
            'resumableChunkNumber' => '1',
            'resumableChunkSize' => '524288',
            'resumableTotalSize' => '100',
            'resumableTotalChunks' => '1',
        ], ['file' => $testFile]);

        $I->seeResponseCodeIs(HttpCode::OK);
        $response = json_decode($I->grabResponse(), true);

        $I->assertNotNull($response, 'Response should be valid JSON');
        $I->assertArrayHasKey('finalFilename', $response, 'Response should have finalFilename');
        $I->assertStringNotContainsString('..', $response['finalFilename']);
        $I->assertStringNotContainsString('/', $response['finalFilename']);
    }

    /**
     * Test upload without file returns 400
     */
    public function uploadWithoutFile(FunctionalTester $I): void
    {
        $I->wantTo('receive 400 when file is missing');

        $identifier = uniqid('nofile-');

        $I->sendPost($this->endpoint, [
            'resumableIdentifier' => $identifier,
            'resumableFilename' => 'test.txt',
            'resumableChunkNumber' => '1',
            'resumableChunkSize' => '524288',
            'resumableTotalSize' => '100',
            'resumableTotalChunks' => '1',
        ]);

        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    /**
     * Test duplicate chunk is ignored
     */
    public function uploadDuplicateChunkIgnored(FunctionalTester $I): void
    {
        $I->wantTo('see duplicate chunk upload is ignored');

        $testFile = $I->createTestFile('dup.txt', 100);
        $identifier = uniqid('dup-');

        $I->sendPost($this->endpoint, [
            'resumableIdentifier' => $identifier,
            'resumableFilename' => 'dup.txt',
            'resumableChunkNumber' => '1',
            'resumableChunkSize' => '524288',
            'resumableTotalSize' => '100',
            'resumableTotalChunks' => '1',
        ], ['file' => $testFile]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['complete' => true]);

        $I->sendPost($this->endpoint, [
            'resumableIdentifier' => $identifier,
            'resumableFilename' => 'dup.txt',
            'resumableChunkNumber' => '1',
            'resumableChunkSize' => '524288',
            'resumableTotalSize' => '100',
            'resumableTotalChunks' => '1',
        ], ['file' => $testFile]);
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    /**
     * Test a body PHP does not parse returns 400 instead of failing
     */
    public function uploadUnparsableBody(FunctionalTester $I): void
    {
        $I->wantTo('receive 400 when the body is not form encoded');

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost($this->endpoint, [
            'resumableIdentifier' => uniqid('json-'),
            'resumableFilename' => 'test.txt',
            'resumableChunkNumber' => '1',
            'resumableChunkSize' => '524288',
            'resumableTotalSize' => '100',
            'resumableTotalChunks' => '1',
        ]);

        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }
}
