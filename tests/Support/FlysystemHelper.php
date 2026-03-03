<?php

declare(strict_types=1);

namespace Blackcube\FileProvider\Tests\Support;

use Blackcube\FileProvider\FlysystemAwsS3;
use Blackcube\FileProvider\FlysystemProviderInterface;
use Blackcube\FileProvider\FlysystemLocal;
use Yiisoft\Aliases\Aliases;

/**
 * Helper to create test filesystem based on .env
 */
final class FlysystemHelper
{
    private static ?string $testId = null;
    private static ?Aliases $aliases = null;

    public static function getTestId(): string
    {
        if (self::$testId === null) {
            self::$testId = uniqid('test-');
        }
        return self::$testId;
    }

    public static function resetTestId(): void
    {
        self::$testId = null;
    }

    public static function getAliases(): Aliases
    {
        if (self::$aliases === null) {
            self::$aliases = new Aliases([
                '@root' => dirname(__DIR__, 2),
                '@data' => dirname(__DIR__, 2) . '/tests/data',
            ]);
        }
        return self::$aliases;
    }

    public static function resolvePath(string $path): string
    {
        if (str_starts_with($path, '@')) {
            return self::getAliases()->get($path);
        }
        return $path;
    }

    public static function createFilesystem(?string $subPath = null): FlysystemProviderInterface
    {
        $type = $_ENV['FILESYSTEM_TYPE'] ?? 'local';

        return match ($type) {
            's3' => self::createS3Filesystem($subPath),
            default => self::createLocalFilesystem($subPath),
        };
    }

    public static function createLocalFilesystem(?string $subPath = null): FlysystemLocal
    {
        $basePath = $_ENV['FILESYSTEM_LOCAL_PATH'] ?? '@data/files';
        $basePath = self::resolvePath($basePath);

        $basePath .= '/' . self::getTestId();

        if ($subPath !== null) {
            $basePath .= '/' . $subPath;
        }

        if (!is_dir($basePath)) {
            mkdir($basePath, 0777, true);
        }

        return new FlysystemLocal($basePath);
    }

    public static function createS3Filesystem(?string $subPath = null): FlysystemAwsS3
    {
        $prefix = self::getTestId();

        if ($subPath !== null) {
            $prefix .= '/' . $subPath;
        }

        return new FlysystemAwsS3(
            bucket: $_ENV['FILESYSTEM_S3_BUCKET'] ?? 'testing',
            key: $_ENV['FILESYSTEM_S3_KEY'] ?? '',
            secret: $_ENV['FILESYSTEM_S3_SECRET'] ?? '',
            region: $_ENV['FILESYSTEM_S3_REGION'] ?? 'eu-east-1',
            endpoint: $_ENV['FILESYSTEM_S3_ENDPOINT'] ?? null,
            prefix: $prefix,
            pathStyleEndpoint: (bool) ($_ENV['FILESYSTEM_S3_PATH_STYLE'] ?? false),
            version: $_ENV['FILESYSTEM_S3_VERSION'] ?? 'latest',
        );
    }

    public static function getType(): string
    {
        return $_ENV['FILESYSTEM_TYPE'] ?? 'local';
    }

    public static function isS3(): bool
    {
        return self::getType() === 's3';
    }

    public static function isLocal(): bool
    {
        return self::getType() === 'local';
    }

    public static function cleanupLocal(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? self::cleanupLocal($path) : unlink($path);
        }
        rmdir($dir);
    }

    public static function cleanupS3(FlysystemProviderInterface $fs): void
    {
        if (!($fs instanceof FlysystemAwsS3)) {
            return;
        }
        try {
            // Delete all files first
            foreach ($fs->listContents('', true) as $item) {
                if ($item->isFile()) {
                    $fs->delete($item->path());
                }
            }
            // Delete directories (now works with placeholders)
            $dirs = [];
            foreach ($fs->listContents('', true) as $item) {
                if ($item->isDir()) {
                    $dirs[] = $item->path();
                }
            }
            rsort($dirs); // Deepest first
            foreach ($dirs as $dir) {
                $fs->deleteDirectory($dir);
            }
        } catch (\Throwable) {
            // Ignore cleanup errors
        }
    }
}
