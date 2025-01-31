<?php

declare(strict_types=1);

namespace Molo\ComposerProxy\Config;

use RuntimeException;

use function filter_var;
use function rtrim;

use const FILTER_VALIDATE_URL;

class PluginConfig
{
    protected bool $enabled = false;
    protected ?string $url;

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getURL(): ?string
    {
        return $this->url;
    }

    public function setURL(?string $url): void
    {
        if ($url === null) {
            $this->url = null;
        } else {
            $this->url = rtrim($url, '/');
        }
    }

    public function validate(): void
    {
        // If set, the URL must be valid
        if (($this->url !== null) && !filter_var($this->url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Invalid URL was set for this plugin');
        }

        // If enabled, a URL must also be set
        if ($this->enabled && ($this->url === null)) {
            throw new RuntimeException('A URL must be set for this plugin to be enabled');
        }
    }
}
