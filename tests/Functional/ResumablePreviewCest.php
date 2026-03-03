<?php

declare(strict_types=1);

namespace Blackcube\FileProvider\Tests\Functional;

use Blackcube\FileProvider\Tests\Support\FunctionalTester;
use Codeception\Util\HttpCode;

/**
 * Functional tests for ResumablePreviewAction
 */
class ResumablePreviewCest
{
    private string $uploadEndpoint = '/fileprovider/upload';
    private string $previewEndpoint = '/fileprovider/preview';

    /**
     * Helper to upload a file and get its final filename
     */
    private function uploadFile(FunctionalTester $I, string $testFile, string $filename): string
    {
        $identifier = uniqid('preview-');

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
     * Preview PNG image from @bltmp
     */
    public function previewTmpPngImage(FunctionalTester $I): void
    {
        $I->wantTo('preview a PNG image from @bltmp');

        $imagePath = $I->createTestImage('test.png', 200, 200);
        $finalFilename = $this->uploadFile($I, $imagePath, 'test.png');

        $I->sendGet($this->previewEndpoint, ['name' => '@bltmp/' . $finalFilename]);

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeHttpHeader('Content-Type', 'image/png');
    }

    /**
     * Preview JPEG image from @bltmp
     */
    public function previewTmpJpegImage(FunctionalTester $I): void
    {
        $I->wantTo('preview a JPEG image from @bltmp');

        $imagePath = $I->createTestImage('photo.jpg', 300, 200);
        $finalFilename = $this->uploadFile($I, $imagePath, 'photo.jpg');

        $I->sendGet($this->previewEndpoint, ['name' => '@bltmp/' . $finalFilename]);

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeHttpHeader('Content-Type', 'image/jpeg');
    }

    /**
     * Preview with original=1 parameter
     */
    public function previewOriginal(FunctionalTester $I): void
    {
        $I->wantTo('get original file without thumbnail');

        $imagePath = $I->createTestImage('big.png', 1000, 1000);
        $finalFilename = $this->uploadFile($I, $imagePath, 'big.png');

        $I->sendGet($this->previewEndpoint, [
            'name' => '@bltmp/' . $finalFilename,
            'original' => '1',
        ]);

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeHttpHeader('Content-Type', 'image/png');
    }

    /**
     * Preview SVG file (should stream directly)
     */
    public function previewSvg(FunctionalTester $I): void
    {
        $I->wantTo('preview SVG file');

        $svgPath = $I->createTestSvg('icon.svg');
        $finalFilename = $this->uploadFile($I, $svgPath, 'icon.svg');

        $I->sendGet($this->previewEndpoint, ['name' => '@bltmp/' . $finalFilename]);

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeHttpHeader('Content-Type', 'image/svg+xml');
    }

    /**
     * Preview non-image file returns icon
     */
    public function previewNonImage(FunctionalTester $I): void
    {
        $I->wantTo('get icon for non-image file');

        $pdfPath = $I->createTestFileWithContent('doc.pdf', '%PDF-1.4 fake pdf content');
        $finalFilename = $this->uploadFile($I, $pdfPath, 'doc.pdf');

        $I->sendGet($this->previewEndpoint, ['name' => '@bltmp/' . $finalFilename]);

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeHttpHeader('Content-Type', 'image/png'); // Icon is PNG
    }

    /**
     * Preview text file returns icon
     */
    public function previewTextFile(FunctionalTester $I): void
    {
        $I->wantTo('get icon for text file');

        $txtPath = $I->createTestFileWithContent('readme.txt', 'Some text content');
        $finalFilename = $this->uploadFile($I, $txtPath, 'readme.txt');

        $I->sendGet($this->previewEndpoint, ['name' => '@bltmp/' . $finalFilename]);

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeHttpHeader('Content-Type', 'image/png'); // Icon is PNG
    }

    /**
     * Preview non-existent file returns 404
     */
    public function previewNotFound(FunctionalTester $I): void
    {
        $I->wantTo('receive 404 for non-existent file');

        $I->sendGet($this->previewEndpoint, ['name' => '@bltmp/notfound.jpg']);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    /**
     * Preview without name parameter returns 400
     */
    public function previewMissingParam(FunctionalTester $I): void
    {
        $I->wantTo('receive 400 when name param is missing');

        $I->sendGet($this->previewEndpoint, []);
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    /**
     * Preview with empty name returns 400
     */
    public function previewEmptyName(FunctionalTester $I): void
    {
        $I->wantTo('receive 400 when name is empty');

        $I->sendGet($this->previewEndpoint, ['name' => '']);
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    /**
     * Preview GIF image
     */
    public function previewGifImage(FunctionalTester $I): void
    {
        $I->wantTo('preview a GIF image');

        $imagePath = $I->createTestImage('animation.gif', 50, 50);
        $finalFilename = $this->uploadFile($I, $imagePath, 'animation.gif');

        $I->sendGet($this->previewEndpoint, ['name' => '@bltmp/' . $finalFilename]);

        $I->seeResponseCodeIs(HttpCode::OK);
        // GIF might be converted to PNG for thumbnail, or stay GIF
        // Just check it returns something
    }
}
