<?php

declare(strict_types=1);

namespace Molo\ComposerProxy\Config;

use UnexpectedValueException;

/**
 * Handles remote configuration from the Composer Proxy server
 */
class RemoteConfig
{
    private ?string $proxyUrl = null;
    /**
     * @var MirrorMapping[]
     */
    protected array $mirrors = [];

    /**
     * Create a RemoteConfig from an array
     *
     * @param array{proxyUrl?: string, mirrors?: array{url?: string, path?: string}[]} $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['mirrors'])) {
            throw new \UnexpectedValueException('Missing `mirrors` key in data');
        }

        $instance = new self();
        
        if (isset($data['proxyUrl'])) {
            $instance->proxyUrl = rtrim($data['proxyUrl'], '/');
        }
        
        foreach ($data['mirrors'] as $mappingData) {
            $instance->addMirror(MirrorMapping::fromArray($mappingData));
        }

        return $instance;
    }

    public function addMirror(MirrorMapping $mapping): void
    {
        $this->mirrors[] = $mapping;
    }

    /**
     * Get the proxy URL
     *
     * @return string|null
     */
    public function getProxyUrl(): ?string
    {
        return $this->proxyUrl;
    }

    /**
     * @return MirrorMapping[]
     */
    public function getMirrors(): array
    {
        return $this->mirrors;
    }

    /**
     * @return array{url?: string, path?: string}[]
     */
    public function getMirrorMappings(): array
    {
        return array_map(function (MirrorMapping $mirror) {
            return [
                'url' => $mirror->getUrl(),
                'path' => $mirror->getPath()
            ];
        }, $this->mirrors);
    }
}
