<?php

declare(strict_types=1);

/**
 * FileProviderInterface.php
 *
 * PHP Version 8.4
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 */

namespace Blackcube\FileProvider\Interfaces;

/**
 * Minimal interface for file providers.
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 */
interface FileProviderInterface
{
    /**
     * Check if the path prefix is handled by this provider
     */
    public function canHandle(string $path): bool;

    /**
     * Check if the path lives in the temporary filesystem (its tmp alias).
     */
    public function isTempPath(string $path): bool;

    /**
     * Alias of the temporary filesystem (resumable uploads land there).
     */
    public function getTempAlias(): string;
}
