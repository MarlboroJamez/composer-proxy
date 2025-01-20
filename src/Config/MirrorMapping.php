<?php

declare(strict_types=1);

namespace Molo\ComposerProxy\Config;

use UnexpectedValueException;

class MirrorMapping
{
    protected string $url;
    protected string $path;

    /**
     * @param array{url?: string, path?: string} $data
     */
    public static function fromArray(array $data): self
    {
        if (!array_key_exists('url', $data) || !array_key_exists('path', $data)) {
            throw new UnexpectedValueException('Missing `url` or `path` key in mirror mapping');
        }

        $mapping = new self();
        $mapping->url = $data['url'];
        $mapping->path = $data['path'];
        return $mapping;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getNormalizedUrl(): string
    {
        return sprintf('%s/', rtrim($this->url, '/'));
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
