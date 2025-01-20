<?php

declare(strict_types=1);

namespace Molo\ComposerProxy\Config;

use RuntimeException;

/**
 * Configuration class for the Composer Proxy plugin
 */
class PluginConfig
{
    protected bool $enabled = false;
    protected ?string $url = null;
    protected ?RemoteConfig $remoteConfig = null;

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param bool $enabled
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * @return string|null
     */
    public function getURL(): ?string
    {
        return $this->url;
    }

    /**
     * @param string|null $url
     */
    public function setURL(?string $url): void
    {
        if ($url === null) {
            $this->url = null;
        } else {
            $this->url = rtrim($url, '/');
        }
    }

    /**
     * @param RemoteConfig|null $config
     */
    public function setRemoteConfig(?RemoteConfig $config): void
    {
        $this->remoteConfig = $config;
    }

    /**
     * @return RemoteConfig|null
     */
    public function getRemoteConfig(): ?RemoteConfig
    {
        return $this->remoteConfig;
    }

    /**
     * Validate the configuration
     *
     * @throws RuntimeException
     */
    public function validate(): void
    {
        // If set, the URL must be valid
        if (($this->url !== null) && !filter_var($this->url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Invalid URL was set for this plugin');
        }

        // If enabled, a URL must also be set
        if ($this->enabled && ($this->url === null)) {
            throw new RuntimeException('No URL was set for this plugin');
        }
    }
}
