<?php

declare(strict_types=1);

namespace Molo\ComposerProxy\Config;

use Composer\Composer;
use Composer\Util\Filesystem;

/**
 * Writes plugin configuration to the filesystem
 */
class PluginConfigWriter
{
    private const CONFIG_FILENAME = 'composer-proxy.json';
    private Filesystem $filesystem;
    private string $configPath;

    /**
     * @param Composer $composer
     */
    public function __construct(Composer $composer)
    {
        $this->filesystem = new Filesystem();
        $this->configPath = $composer->getConfig()->get('home') . '/' . self::CONFIG_FILENAME;
    }

    /**
     * Write configuration to file
     *
     * @param PluginConfig $config
     * @return void
     */
    public function write(PluginConfig $config): void
    {
        $this->filesystem->ensureDirectoryExists(dirname($this->configPath));
        
        $data = [
            'enabled' => $config->isEnabled(),
            'proxyUrl' => $config->getProxyUrl(),
        ];
        
        file_put_contents($this->configPath, json_encode($data, JSON_PRETTY_PRINT));
    }
}
