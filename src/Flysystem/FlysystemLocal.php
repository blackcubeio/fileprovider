<?php

declare(strict_types=1);

/**
 * FlysystemLocal.php
 *
 * PHP Version 8.4
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 */

namespace Blackcube\FileProvider\Flysystem;

use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\PathNormalizer;
use League\Flysystem\UnixVisibility\VisibilityConverter;
use League\MimeTypeDetection\MimeTypeDetector;

/**
 * Local filesystem adapter.
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 */
class FlysystemLocal extends Flysystem
{
    public function __construct(
        private readonly string $path,
        private readonly ?VisibilityConverter $visibility = null,
        private readonly int $writeFlags = LOCK_EX,
        private readonly int $linkHandling = LocalFilesystemAdapter::DISALLOW_LINKS,
        private readonly ?MimeTypeDetector $mimeTypeDetector = null,
        array $config = [],
        ?PathNormalizer $pathNormalizer = null,
    ) {
        parent::__construct($config, $pathNormalizer);
    }

    protected function prepareAdapter(): FilesystemAdapter
    {
        return new LocalFilesystemAdapter(
            $this->path,
            $this->visibility,
            $this->writeFlags,
            $this->linkHandling,
            $this->mimeTypeDetector,
        );
    }
}
