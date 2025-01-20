<?php

declare(strict_types=1);

namespace Molo\ComposerProxy\Config;

use UnexpectedValueException;

use function array_key_exists;

/**
 * Handles remote configuration from the Composer Proxy server
 */
class RemoteConfig
{
    /**
     * @var MirrorMapping[]
     */
    protected array $mirrors = [];

    /**
     * Create a RemoteConfig from an array
     *
     * @param array{mirrors?: array{url?: string, path?: string}[]} $data
     * @return self
     */
    public static function fromArray(array $data): RemoteConfig
    {
        $config = new RemoteConfig();

        if (!array_key_exists('mirrors', $data)) {
            throw new UnexpectedValueException('Missing `mirrors` key in data');
        }
        foreach ($data['mirrors'] as $mappingData) {
            $config->addMirror(MirrorMapping::fromArray($mappingData));
        }

        return $config;
    }

    public function addMirror(MirrorMapping $mapping): void
    {
        $this->mirrors[] = $mapping;
    }

    /**
     * @return MirrorMapping[]
     */
    public function getMirrors(): array
    {
        return $this->mirrors;
    }
}
