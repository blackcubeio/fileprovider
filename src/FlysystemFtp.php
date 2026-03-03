<?php

declare(strict_types=1);

/**
 * FlysystemFtp.php
 *
 * PHP Version 8.3+
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */

namespace Blackcube\FileProvider;

use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Ftp\ConnectivityChecker;
use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Ftp\FtpConnectionOptions;
use League\Flysystem\Ftp\FtpConnectionProvider;
use League\Flysystem\PathNormalizer;
use League\Flysystem\UnixVisibility\VisibilityConverter;
use League\MimeTypeDetection\MimeTypeDetector;

/**
 * FTP adapter.
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */
class FlysystemFtp extends Flysystem
{
    public function __construct(
        private readonly string $host,
        private readonly string $root,
        private readonly string $username,
        private readonly string $password,
        private readonly int $port = 21,
        private readonly bool $ssl = false,
        private readonly int $timeout = 90,
        private readonly bool $utf8 = false,
        private readonly bool $passive = true,
        private readonly int $transferMode = FTP_BINARY,
        private readonly ?string $systemType = null,
        private readonly ?bool $ignorePassiveAddress = null,
        private readonly bool $timestampsOnUnixListingsEnabled = false,
        private readonly bool $recurseManually = true,
        private readonly ?VisibilityConverter $visibility = null,
        private readonly ?FtpConnectionProvider $connectionProvider = null,
        private readonly ?ConnectivityChecker $connectivityChecker = null,
        private readonly ?MimeTypeDetector $mimeTypeDetector = null,
        array $config = [],
        ?PathNormalizer $pathNormalizer = null,
    ) {
        parent::__construct($config, $pathNormalizer);
    }

    protected function prepareAdapter(): FilesystemAdapter
    {
        $options = FtpConnectionOptions::fromArray([
            'host' => $this->host,
            'root' => $this->root,
            'username' => $this->username,
            'password' => $this->password,
            'port' => $this->port,
            'ssl' => $this->ssl,
            'timeout' => $this->timeout,
            'utf8' => $this->utf8,
            'passive' => $this->passive,
            'transferMode' => $this->transferMode,
            'systemType' => $this->systemType,
            'ignorePassiveAddress' => $this->ignorePassiveAddress,
            'timestampsOnUnixListingsEnabled' => $this->timestampsOnUnixListingsEnabled,
            'recurseManually' => $this->recurseManually,
        ]);

        return new FtpAdapter(
            $options,
            $this->connectionProvider,
            $this->connectivityChecker,
            $this->visibility,
            $this->mimeTypeDetector,
        );
    }
}
