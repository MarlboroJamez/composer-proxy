<?php

declare(strict_types=1);

namespace Molo\ComposerProxy\Config;

use UnexpectedValueException;

use function array_key_exists;
use function is_array;

class RemoteConfig
{
    /**
     * @var MirrorMapping[]
     */
    protected array $mirrors = [];

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): RemoteConfig
    {
        $config = new RemoteConfig();

        // Handle different possible response formats
        $mirrors = [];
        
        // Format 1: { "mirrors": [...] }
        if (array_key_exists('mirrors', $data) && is_array($data['mirrors'])) {
            $mirrors = $data['mirrors'];
        }
        // Format 2: { "repositories": [...] }
        elseif (array_key_exists('repositories', $data) && is_array($data['repositories'])) {
            $mirrors = $data['repositories'];
        }
        // Format 3: Direct array of mirrors
        elseif (!empty($data) && !array_key_exists('mirrors', $data) && !array_key_exists('repositories', $data)) {
            $mirrors = $data;
        }

        // If we still have no mirrors, create a default mirror for the proxy URL
        if (empty($mirrors)) {
            $mirrors = [
                [
                    'url' => 'https://packagist.org',
                    'path' => 'proxy'
                ]
            ];
        }

        foreach ($mirrors as $mappingData) {
            if (!is_array($mappingData)) {
                continue;
            }
            try {
                $config->addMirror(MirrorMapping::fromArray($mappingData));
            } catch (UnexpectedValueException $e) {
                // Skip invalid mirror configurations
                continue;
            }
        }

        // If we have no valid mirrors after processing, throw an exception
        if (empty($config->getMirrors())) {
            throw new UnexpectedValueException('No valid mirror configurations found in remote config');
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
