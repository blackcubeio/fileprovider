<?php

declare(strict_types=1);

/**
 * configuration.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

return [
    'config-plugin' => [
        'params' => 'common/params.php',
        'di' => 'common/di.php',
        'bootstrap-web' => 'web/bootstrap.php',
    ],
    'config-plugin-options' => [
        'source-directory' => 'config',
    ],
];
