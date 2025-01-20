<?php

declare(strict_types=1);

namespace Molo\ComposerProxy\Config;

use RuntimeException;

use function dirname;
use function file_put_contents;
use function is_dir;
use function json_encode;
use function mkdir;
use function sprintf;

use const JSON_PRETTY_PRINT;

/**
 * Writes plugin configuration to the filesystem
 */
class PluginConfigWriter
{
    protected PluginConfig $config;

    /**
     * @param PluginConfig $config
     */
    public function __construct(PluginConfig $config)
    {
        $this->config = $config;
    }

    /**
     * Write configuration to file
     *
     * @param string $path
     * @return void
     */
    public function write(string $path): void
    {
        $this->config->validate();

        $configDir = dirname($path);
        if (!is_dir($configDir) && !mkdir($configDir, 0777, true)) {
            throw new RuntimeException(sprintf('Failed to create directory %s', $configDir));
        }

        $data = [
            'enabled' => $this->config->isEnabled(),
            'url' => $this->config->getURL(),
        ];

        if (file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT)) === false) {
            throw new RuntimeException(sprintf('Failed to write configuration to %s', $path));
        }
    }
}
