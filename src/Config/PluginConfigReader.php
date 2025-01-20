<?php

declare(strict_types=1);

namespace Molo\ComposerProxy\Config;

use Composer\Composer;
use Composer\Util\Filesystem;
use RuntimeException;

/**
 * Reads plugin configuration from the filesystem
 */
class PluginConfigReader
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
     * @param array{enabled?: bool, proxyUrl?: string, url?: string} $payload
     */
    protected function getPluginConfigForPayload(array $payload): PluginConfig
    {
        $config = new PluginConfig();
        if (array_key_exists('enabled', $payload)) {
            $config->setEnabled($payload['enabled']);
        }
        if (array_key_exists('proxyUrl', $payload)) {
            $config->setProxyUrl($payload['proxyUrl']);
        } elseif (array_key_exists('url', $payload)) {
            $config->setProxyUrl($payload['url']);
        }
        return $config;
    }

    /**
     * @throws RuntimeException
     */
    public function read(string $path): PluginConfig
    {
        if (!is_readable($path)) {
            throw new RuntimeException('Unable to read configuration');
        }

        $data = file_get_contents($path);
        if ($data === false) {
            throw new RuntimeException('Failed to read configuration');
        }

        $data = json_decode($data, true);
        if (!is_array($data)) {
            throw new RuntimeException('Could not decode configuration JSON');
        }

        return $this->getPluginConfigForPayload($data);
    }

    public function readOrNew(string $path): PluginConfig
    {
        try {
            return $this->read($path);
        } catch (RuntimeException $e) {
            return new PluginConfig();
        }
    }
}
