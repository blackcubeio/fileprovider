<?php

declare(strict_types=1);

/**
 * FileProviderInterface.php
 *
 * PHP Version 8.3+
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */

namespace Blackcube\FileProvider\Contracts;

/**
 * Minimal interface for file providers.
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */
interface FileProviderInterface
{
    /**
     * Check if the path prefix is handled by this provider
     */
    public function canHandle(string $path): bool;
}
