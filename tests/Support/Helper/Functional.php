<?php

declare(strict_types=1);

namespace Blackcube\FileProvider\Tests\Support\Helper;

use Blackcube\FileProvider\Tests\Support\FlysystemHelper;
use Codeception\Module;
use Codeception\TestInterface;

/**
 * Helper for functional tests
 *
 * Provides utilities to create test files and manage test directories
 */
class Functional extends Module
{
    private string $testId;
    private string $localTmpPath;

    public function _before(TestInterface $test): void
    {
        $this->testId = uniqid('func-');
        $this->localTmpPath = sys_get_temp_dir() . '/fileprovider-' . $this->testId;

        // Create local temp directories for test file creation
        mkdir($this->localTmpPath, 0777, true);
    }

    public function _after(TestInterface $test): void
    {
        // Cleanup local temp
        $this->deleteDirectory($this->localTmpPath);
    }

    public function getTestId(): string
    {
        return $this->testId;
    }

    /**
     * Get local temp path for creating test files before upload
     */
    public function getLocalTmpPath(): string
    {
        return $this->localTmpPath;
    }

    /**
     * Create a test file with random content
     */
    public function createTestFile(string $name, int $size = 1024): string
    {
        $path = $this->localTmpPath . '/' . $name;
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $content = random_bytes($size);
        file_put_contents($path, $content);

        return $path;
    }

    /**
     * Create a test file with specific content
     */
    public function createTestFileWithContent(string $name, string $content): string
    {
        $path = $this->localTmpPath . '/' . $name;
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path, $content);

        return $path;
    }

    /**
     * Create a test PNG image
     */
    public function createTestImage(string $name, int $width = 100, int $height = 100): string
    {
        $path = $this->localTmpPath . '/' . $name;
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $image = imagecreatetruecolor($width, $height);
        if ($image === false) {
            throw new \RuntimeException('Failed to create image');
        }

        $color = imagecolorallocate($image, rand(0, 255), rand(0, 255), rand(0, 255));
        if ($color === false) {
            $color = 0;
        }
        imagefill($image, 0, 0, $color);

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        match ($extension) {
            'jpg', 'jpeg' => imagejpeg($image, $path, 90),
            'gif' => imagegif($image, $path),
            default => imagepng($image, $path),
        };

        imagedestroy($image);

        return $path;
    }

    /**
     * Create a test SVG file
     */
    public function createTestSvg(string $name): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">'
            . '<circle cx="50" cy="50" r="40" fill="#' . dechex(rand(0, 16777215)) . '"/>'
            . '</svg>';

        return $this->createTestFileWithContent($name, $svg);
    }

    /**
     * Split a file into chunks for testing multi-chunk upload
     *
     * @return array<int, string> Array of chunk file paths indexed by chunk number (1-based)
     */
    public function splitFileIntoChunks(string $filePath, int $chunkSize): array
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException("Cannot read file: {$filePath}");
        }

        $chunks = [];
        $totalSize = strlen($content);
        $numChunks = (int) ceil($totalSize / $chunkSize);

        for ($i = 0; $i < $numChunks; $i++) {
            $chunkContent = substr($content, $i * $chunkSize, $chunkSize);
            $chunkPath = $this->localTmpPath . '/chunk_' . ($i + 1) . '.bin';
            file_put_contents($chunkPath, $chunkContent);
            $chunks[$i + 1] = $chunkPath;
        }

        return $chunks;
    }

    /**
     * Get file content
     */
    public function getFileContent(string $path): string
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Cannot read file: {$path}");
        }
        return $content;
    }

    /**
     * Delete a directory recursively
     */
    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = scandir($dir);
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
