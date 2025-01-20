<?php

declare(strict_types=1);

namespace Molo\ComposerProxy\Config;

/**
 * Configuration class for the Composer Proxy plugin
 */
class PluginConfig
{
    protected bool $enabled = false;
    protected ?string $proxyUrl = null;

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
    public function getProxyUrl(): ?string
    {
        return $this->proxyUrl;
    }

    /**
     * @param string $proxyUrl
     */
    public function setProxyUrl(string $proxyUrl): void
    {
        $this->proxyUrl = $proxyUrl;
    }

    /**
     * @return array
     */
    public function getMirrorMappings(): array
    {
        return [];
    }

    /**
     * @param array $mirrorMappings
     * @return self
     */
    public function setMirrorMappings(array $mirrorMappings): self
    {
        // This method is not used anywhere in the class, so it's left as is
        $this->mirrorMappings = $mirrorMappings;
        return $this;
    }

    /**
     * Convert config to array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'proxy_url' => $this->proxyUrl,
            'mirror_mappings' => $this->getMirrorMappings(),
        ];
    }

    /**
     * Create config from array
     *
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $config = new self();
        $config->setEnabled($data['enabled'] ?? false)
            ->setProxyUrl($data['proxy_url'] ?? '');
        return $config;
    }
}
