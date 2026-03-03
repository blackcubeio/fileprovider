<?php

declare(strict_types=1);

/**
 * bootstrap.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

use Psr\Container\ContainerInterface;
use Yiisoft\Aliases\Aliases;

return [
    static function (ContainerInterface $container): void {
        // Register @fileprovider alias for package resources
        $aliases = $container->get(Aliases::class);
        $aliases->set('@fileprovider', dirname(__DIR__, 2) . '/src/resources');
    },
];
