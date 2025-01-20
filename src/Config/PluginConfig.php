<?php

declare(strict_types=1);

namespace Molo\ComposerProxy\Config;

use RuntimeException;

use function filter_var;
use function rtrim;

use const FILTER_VALIDATE_URL;

/**
 * Configuration class for the Composer Proxy plugin
 */
class PluginConfig
{
    protected bool $enabled = false;
    protected ?string $url = null;

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
