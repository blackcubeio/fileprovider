<?php

declare(strict_types=1);

/**
 * UnknownFilesystemException.php
 *
 * PHP Version 8.4
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 */

namespace Blackcube\FileProvider\Exception;

use InvalidArgumentException;

/**
 * Exception thrown when a filesystem alias is not found.
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 */
class UnknownFilesystemException extends InvalidArgumentException
{
}
