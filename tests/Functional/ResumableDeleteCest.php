<?php

declare(strict_types=1);

namespace Blackcube\FileProvider\Tests\Functional;

use Blackcube\FileProvider\Tests\Support\FunctionalTester;
use Codeception\Util\HttpCode;

/**
 * Functional tests for ResumableDeleteAction
 *
 * Tests follow Resumable.js protocol:
 * - DELETE requests have name parameter in URL query string (not body)
 * - URL format: /fileprovider/delete?name=@bltmp/filename.ext
 */
class ResumableDeleteCest
{
    private string $uploadEndpoint = '/fileprovider/upload';
    private string $previewEndpoint = '/fileprovider/preview';
    private string $deleteEndpoint = '/fileprovider/delete';

    /**
     * Helper to upload a file and get its final filename
     */
    private function uploadFile(FunctionalTester $I, string $testFile, string $filename): string
    {
        $identifier = uniqid('delete-');

        $I->sendPost($this->uploadEndpoint, [
            'resumableIdentifier' => $identifier,
            'resumableFilename' => $filename,
            'resumableChunkNumber' => '1',
            'resumableChunkSize' => '524288',
            'resumableTotalSize' => (string) filesize($testFile),
            'resumableTotalChunks' => '1',
        ], ['file' => $testFile]);

        $response = json_decode($I->grabResponse(), true);
        return $response['finalFilename'];
    }

    /**
     * Delete temporary file from @bltmp
     */
    public function deleteTmpFile(FunctionalTester $I): void
    {
        $I->wantTo('delete a temporary file');

        // Upload a file first
        $testFile = $I->createTestFile('todelete.txt', 100);
        $finalFilename = $this->uploadFile($I, $testFile, 'todelete.txt');

        // Verify it exists via preview
        $I->sendGet($this->previewEndpoint, ['name' => '@bltmp/' . $finalFilename]);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Delete it (Resumable.js sends name in URL query string)
        $I->sendDelete($this->deleteEndpoint . '?name=' . urlencode('@bltmp/' . $finalFilename));
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);

        // Verify it's gone
        $I->sendGet($this->previewEndpoint, ['name' => '@bltmp/' . $finalFilename]);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    /**
     * Cannot delete @blfs files (forbidden)
     */
    public function cannotDeleteFsFile(FunctionalTester $I): void
    {
        $I->wantTo('be forbidden to delete @blfs files');

        $I->sendDelete($this->deleteEndpoint . '?name=' . urlencode('@blfs/protected.txt'));
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    /**
     * Delete non-existent file returns 204 (idempotent)
     */
    public function deleteNonExistent(FunctionalTester $I): void
    {
        $I->wantTo('receive 204 even if file does not exist');

        $I->sendDelete($this->deleteEndpoint . '?name=' . urlencode('@bltmp/notfound.txt'));
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
    }

    /**
     * Delete without name parameter returns 400
     */
    public function deleteMissingParam(FunctionalTester $I): void
    {
        $I->wantTo('receive 400 when name param is missing');

        $I->sendDelete($this->deleteEndpoint);
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    /**
     * Delete with empty name returns 400
     */
    public function deleteEmptyName(FunctionalTester $I): void
    {
        $I->wantTo('receive 400 when name is empty');

        $I->sendDelete($this->deleteEndpoint . '?name=');
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    /**
     * Path traversal is blocked
     */
    public function deletePathTraversal(FunctionalTester $I): void
    {
        $I->wantTo('be protected against path traversal');

        // Try to delete with path traversal
        $I->sendDelete($this->deleteEndpoint . '?name=' . urlencode('@bltmp/../outside.txt'));
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    /**
     * Delete image file
     */
    public function deleteImageFile(FunctionalTester $I): void
    {
        $I->wantTo('delete an uploaded image');

        // Upload an image
        $imagePath = $I->createTestImage('photo.png');
        $finalFilename = $this->uploadFile($I, $imagePath, 'photo.png');

        // Verify it exists
        $I->sendGet($this->previewEndpoint, ['name' => '@bltmp/' . $finalFilename]);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Delete it
        $I->sendDelete($this->deleteEndpoint . '?name=' . urlencode('@bltmp/' . $finalFilename));
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);

        // Verify it's gone
        $I->sendGet($this->previewEndpoint, ['name' => '@bltmp/' . $finalFilename]);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    /**
     * Multiple deletes of same file (idempotent)
     */
    public function deleteMultipleTimes(FunctionalTester $I): void
    {
        $I->wantTo('delete same file multiple times (idempotent)');

        // Upload a file
        $testFile = $I->createTestFile('multi.txt', 50);
        $finalFilename = $this->uploadFile($I, $testFile, 'multi.txt');

        $deleteUrl = $this->deleteEndpoint . '?name=' . urlencode('@bltmp/' . $finalFilename);

        // Delete multiple times - all should return 204
        $I->sendDelete($deleteUrl);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);

        $I->sendDelete($deleteUrl);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);

        $I->sendDelete($deleteUrl);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
    }
}
