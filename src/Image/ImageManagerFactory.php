<?php

declare(strict_types=1);

/**
 * ImageManagerFactory.php
 *
 * PHP Version 8.4
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 */

namespace Blackcube\FileProvider\Image;

use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\DriverInterface;

/**
 * Creates ImageManager with auto-detection.
 *
 * Priority: vips > imagick > gd
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 */
class ImageManagerFactory
{
    private const DRIVER_GD = 'Intervention\Image\Drivers\Gd\Driver';
    private const DRIVER_IMAGICK = 'Intervention\Image\Drivers\Imagick\Driver';
    private const DRIVER_VIPS = 'Intervention\Image\Drivers\Vips\Driver';

    public static function create(?string $driver = null): ?ImageManager
    {
        if (class_exists(ImageManager::class) === false) {
            return null;
        }

        if ($driver !== null) {
            $driverInstance = self::driverFromName($driver);
            if ($driverInstance === null) {
                throw new \InvalidArgumentException('Driver "'.$driver.'" is not available');
            }
            return new ImageManager($driverInstance);
        }

        if (extension_loaded('vips') === true && class_exists(self::DRIVER_VIPS) === true) {
            return new ImageManager(new (self::DRIVER_VIPS)());
        }

        if (extension_loaded('imagick') === true && class_exists(self::DRIVER_IMAGICK) === true) {
            return new ImageManager(new (self::DRIVER_IMAGICK)());
        }

        if (class_exists(self::DRIVER_GD) === true) {
            return new ImageManager(new (self::DRIVER_GD)());
        }

        return null;
    }

    private static function driverFromName(string $name): ?DriverInterface
    {
        return match ($name) {
            'vips' => class_exists(self::DRIVER_VIPS) === true ? new (self::DRIVER_VIPS)() : null,
            'imagick' => class_exists(self::DRIVER_IMAGICK) === true ? new (self::DRIVER_IMAGICK)() : null,
            'gd' => class_exists(self::DRIVER_GD) === true ? new (self::DRIVER_GD)() : null,
            default => throw new \InvalidArgumentException('Unknown driver: '.$name),
        };
    }
}
