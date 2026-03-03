<?php

declare(strict_types=1);

namespace Blackcube\FileProvider\Tests\Functional;

use Blackcube\FileProvider\Tests\Support\FunctionalTester;
use Codeception\Util\HttpCode;

/**
 * Functional tests for ResumableUploadAction
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

        // Split into chunks
        $chunks = $I->splitFileIntoChunks($testFile, $chunkSize);
        $numChunks = count($chunks);

        // Upload all chunks
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
        $totalSize = 1000; // Will need 3 chunks

        // GET without uploaded chunk → 204 (chunk doesn't exist)
        $I->sendGet($this->endpoint, [
            'resumableIdentifier' => $identifier,
            'resumableFilename' => 'test.txt',
            'resumableChunkNumber' => '1',
        ]);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);

        // Upload chunk 1 only (incomplete upload, 3 chunks needed)
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
        $I->seeResponseContainsJson(['complete' => false]); // Not complete yet

        // GET with uploaded chunk → 200 (chunk exists)
        $I->sendGet($this->endpoint, [
            'resumableIdentifier' => $identifier,
            'resumableFilename' => 'test.txt',
            'resumableChunkNumber' => '1',
        ]);
        $I->seeResponseCodeIs(HttpCode::OK);

        // GET for non-uploaded chunk → 204 (chunk doesn't exist)
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

        // GET without params → 400
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

        // Filename must be sanitized - no path components allowed
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

        // First upload
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

        // Second upload (duplicate) - should still succeed
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
}
