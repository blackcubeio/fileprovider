<?php

declare(strict_types=1);

namespace Blackcube\FileProvider\Tests\Functional;

use Blackcube\FileProvider\Tests\Support\FunctionalTester;
use Codeception\Util\HttpCode;

/**
 * End-to-end functional tests for the complete Resumable.js upload flow
 *
 * Tests follow Resumable.js protocol:
 * - Upload: POST multipart/form-data with resumable* params + file
 * - Check chunk: GET with resumable* params in query string
 * - Preview: GET with name in query string
 * - Delete: DELETE with name in query string (no body)
 */
class ResumableFlowCest
{
    private string $uploadEndpoint = '/fileprovider/upload';
    private string $previewEndpoint = '/fileprovider/preview';
    private string $deleteEndpoint = '/fileprovider/delete';

    /**
     * Complete flow: upload → preview → delete
     */
    public function fullUploadFlow(FunctionalTester $I): void
    {
        $I->wantTo('complete full upload flow: upload → preview → delete');

        // 1. Upload
        $testFile = $I->createTestImage('photo.png', 150, 150);
        $identifier = uniqid('flow-');

        $I->sendPost($this->uploadEndpoint, [
            'resumableIdentifier' => $identifier,
            'resumableFilename' => 'photo.png',
            'resumableChunkNumber' => '1',
            'resumableChunkSize' => '524288',
            'resumableTotalSize' => (string) filesize($testFile),
            'resumableTotalChunks' => '1',
        ], ['file' => $testFile]);

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['complete' => true]);

        $response = json_decode($I->grabResponse(), true);
        $finalFilename = $response['finalFilename'];
        $I->assertNotEmpty($finalFilename);

        // 2. Preview
        $I->sendGet($this->previewEndpoint, ['name' => '@bltmp/' . $finalFilename]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeHttpHeader('Content-Type', 'image/png');

        // 3. Delete (Resumable.js uses query params, not body)
        $I->sendDelete($this->deleteEndpoint . '?name=' . urlencode('@bltmp/' . $finalFilename));
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);

        // 4. Verify deleted
        $I->sendGet($this->previewEndpoint, ['name' => '@bltmp/' . $finalFilename]);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    /**
     * Multi-chunk upload flow
     */
    public function multiChunkUploadFlow(FunctionalTester $I): void
    {
        $I->wantTo('complete multi-chunk upload flow');

        $chunkSize = 500;
        $totalSize = 1200;
        $testFile = $I->createTestFile('largefile.bin', $totalSize);
        $identifier = uniqid('multi-flow-');

        // Split into chunks
        $chunks = $I->splitFileIntoChunks($testFile, $chunkSize);

        // Upload chunks
        $finalFilename = null;
        $numChunks = count($chunks);
        foreach ($chunks as $chunkNumber => $chunkPath) {
            $I->sendPost($this->uploadEndpoint, [
                'resumableIdentifier' => $identifier,
                'resumableFilename' => 'largefile.bin',
                'resumableChunkNumber' => (string) $chunkNumber,
                'resumableChunkSize' => (string) $chunkSize,
                'resumableTotalSize' => (string) $totalSize,
                'resumableTotalChunks' => (string) $numChunks,
            ], ['file' => $chunkPath]);

            $I->seeResponseCodeIs(HttpCode::OK);

            $response = json_decode($I->grabResponse(), true);
            if ($response['complete']) {
                $finalFilename = $response['finalFilename'];
            }
        }

        $I->assertNotNull($finalFilename, 'Upload should be complete');

        // Preview (non-image returns icon)
        $I->sendGet($this->previewEndpoint, ['name' => '@bltmp/' . $finalFilename]);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Delete
        $I->sendDelete($this->deleteEndpoint . '?name=' . urlencode('@bltmp/' . $finalFilename));
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
    }

    /**
     * Resume interrupted upload (test chunk existence via GET)
     */
    public function resumeInterruptedUpload(FunctionalTester $I): void
    {
        $I->wantTo('resume an interrupted upload');

        $chunkSize = 400;
        $totalSize = 1000;
        $testFile = $I->createTestFile('resume.bin', $totalSize);
        $identifier = uniqid('resume-flow-');

        $chunks = $I->splitFileIntoChunks($testFile, $chunkSize);

        // Upload first chunk only
        $numChunks = count($chunks);
        $I->sendPost($this->uploadEndpoint, [
            'resumableIdentifier' => $identifier,
            'resumableFilename' => 'resume.bin',
            'resumableChunkNumber' => '1',
            'resumableChunkSize' => (string) $chunkSize,
            'resumableTotalSize' => (string) $totalSize,
            'resumableTotalChunks' => (string) $numChunks,
        ], ['file' => $chunks[1]]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['complete' => false]);

        // Simulate "resume" - check which chunks exist (Resumable.js uses GET)
        $I->sendGet($this->uploadEndpoint, [
            'resumableIdentifier' => $identifier,
            'resumableFilename' => 'resume.bin',
            'resumableChunkNumber' => '1',
        ]);
        $I->seeResponseCodeIs(HttpCode::OK); // Chunk 1 exists

        $I->sendGet($this->uploadEndpoint, [
            'resumableIdentifier' => $identifier,
            'resumableFilename' => 'resume.bin',
            'resumableChunkNumber' => '2',
        ]);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT); // Chunk 2 doesn't exist

        // Upload remaining chunks
        for ($i = 2; $i <= count($chunks); $i++) {
            $I->sendPost($this->uploadEndpoint, [
                'resumableIdentifier' => $identifier,
                'resumableFilename' => 'resume.bin',
                'resumableChunkNumber' => (string) $i,
                'resumableChunkSize' => (string) $chunkSize,
                'resumableTotalSize' => (string) $totalSize,
                'resumableTotalChunks' => (string) $numChunks,
            ], ['file' => $chunks[$i]]);
            $I->seeResponseCodeIs(HttpCode::OK);
        }

        // Last response should indicate complete
        $response = json_decode($I->grabResponse(), true);
        $I->assertTrue($response['complete']);
        $I->assertNotEmpty($response['finalFilename']);
    }

    /**
     * Upload multiple files sequentially
     */
    public function uploadMultipleFiles(FunctionalTester $I): void
    {
        $I->wantTo('upload multiple files sequentially');

        $files = [
            ['name' => 'image1.png', 'type' => 'image'],
            ['name' => 'document.txt', 'type' => 'text'],
            ['name' => 'image2.jpg', 'type' => 'image'],
        ];

        $uploadedFiles = [];

        foreach ($files as $file) {
            $identifier = uniqid('multi-');

            if ($file['type'] === 'image') {
                $testFile = $I->createTestImage($file['name']);
            } else {
                $testFile = $I->createTestFile($file['name'], 200);
            }

            $I->sendPost($this->uploadEndpoint, [
                'resumableIdentifier' => $identifier,
                'resumableFilename' => $file['name'],
                'resumableChunkNumber' => '1',
                'resumableChunkSize' => '524288',
                'resumableTotalSize' => (string) filesize($testFile),
                'resumableTotalChunks' => '1',
            ], ['file' => $testFile]);

            $I->seeResponseCodeIs(HttpCode::OK);
            $I->seeResponseContainsJson(['complete' => true]);

            $response = json_decode($I->grabResponse(), true);
            $uploadedFiles[] = $response['finalFilename'];
        }

        // Verify all files exist
        foreach ($uploadedFiles as $filename) {
            $I->sendGet($this->previewEndpoint, ['name' => '@bltmp/' . $filename]);
            $I->seeResponseCodeIs(HttpCode::OK);
        }

        // Clean up
        foreach ($uploadedFiles as $filename) {
            $I->sendDelete($this->deleteEndpoint . '?name=' . urlencode('@bltmp/' . $filename));
            $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
        }
    }

    /**
     * Upload SVG and verify correct content-type
     */
    public function uploadAndPreviewSvg(FunctionalTester $I): void
    {
        $I->wantTo('upload SVG and verify correct preview');

        $svgFile = $I->createTestSvg('logo.svg');
        $identifier = uniqid('svg-');

        $I->sendPost($this->uploadEndpoint, [
            'resumableIdentifier' => $identifier,
            'resumableFilename' => 'logo.svg',
            'resumableChunkNumber' => '1',
            'resumableChunkSize' => '524288',
            'resumableTotalSize' => (string) filesize($svgFile),
            'resumableTotalChunks' => '1',
        ], ['file' => $svgFile]);

        $I->seeResponseCodeIs(HttpCode::OK);
        $response = json_decode($I->grabResponse(), true);
        $finalFilename = $response['finalFilename'];

        // SVG should return as image/svg+xml
        $I->sendGet($this->previewEndpoint, ['name' => '@bltmp/' . $finalFilename]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeHttpHeader('Content-Type', 'image/svg+xml');
    }
}
