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
        if (!isset($data['url']) || !isset($data['path'])) {
            throw new UnexpectedValueException('Missing `url` or `path` key in mirror mapping');
        }

        $instance = new self();
        $instance->url = $data['url'];
        $instance->path = $data['path'];
        return $instance;
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
