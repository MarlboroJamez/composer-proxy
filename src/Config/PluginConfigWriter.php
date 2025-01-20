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
    private PluginConfig $pluginConfig;

    /**
     * @param PluginConfig $pluginConfig
     */
    public function __construct(PluginConfig $pluginConfig)
    {
        $this->pluginConfig = $pluginConfig;
    }

    /**
     * @return array{enabled: bool, url: ?string}
     */
    protected function getPayload(): array
    {
        return [
            'enabled' => $this->pluginConfig->isEnabled(),
            'url'     => $this->pluginConfig->getURL(),
        ];
    }

    /**
     * Write configuration to file
     *
     * @param string $path
     * @return void
     */
    public function write(string $path): void
    {
        $this->pluginConfig->validate();

        // Ensure parent directory exists
        $configDir = dirname($path);
        if (!is_dir($configDir) && !mkdir($configDir, 0777, true)) {
            throw new RuntimeException(sprintf('Failed to create directory %s', $configDir));
        }

        $configJSON = json_encode($this->getPayload(), JSON_PRETTY_PRINT);
        if (file_put_contents($path, $configJSON) === false) {
            throw new RuntimeException(sprintf('Failed to write configuration to %s', $path));
        }
    }
}
