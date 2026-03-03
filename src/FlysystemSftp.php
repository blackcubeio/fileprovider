<?php

declare(strict_types=1);

/**
 * FlysystemSftp.php
 *
 * PHP Version 8.3+
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */

namespace Blackcube\FileProvider;

use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathNormalizer;
use League\Flysystem\PhpseclibV3\ConnectivityChecker;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;
use League\Flysystem\UnixVisibility\VisibilityConverter;
use League\MimeTypeDetection\MimeTypeDetector;

/**
 * SFTP adapter.
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */
class FlysystemSftp extends Flysystem
{
    public function __construct(
        private readonly string $host,
        private readonly string $username,
        private readonly string $root,
        private readonly ?string $password = null,
        private readonly ?string $privateKey = null,
        private readonly ?string $passphrase = null,
        private readonly int $port = 22,
        private readonly bool $useAgent = false,
        private readonly int $timeout = 10,
        private readonly int $maxTries = 4,
        private readonly ?string $fingerprint = null,
        private readonly ?VisibilityConverter $visibility = null,
        private readonly ?ConnectivityChecker $connectivityChecker = null,
        private readonly ?MimeTypeDetector $mimeTypeDetector = null,
        array $config = [],
        ?PathNormalizer $pathNormalizer = null,
    ) {
        parent::__construct($config, $pathNormalizer);
    }

    protected function prepareAdapter(): FilesystemAdapter
    {
        $connectionProvider = new SftpConnectionProvider(
            $this->host,
            $this->username,
            $this->password,
            $this->privateKey,
            $this->passphrase,
            $this->port,
            $this->useAgent,
            $this->timeout,
            $this->maxTries,
            $this->fingerprint,
            $this->connectivityChecker,
        );

        return new SftpAdapter(
            $connectionProvider,
            $this->root,
            $this->visibility,
            $this->mimeTypeDetector,
        );
    }
}
