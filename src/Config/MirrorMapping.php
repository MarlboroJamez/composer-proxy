<?php

declare(strict_types=1);

namespace Molo\ComposerProxy\Config;

use UnexpectedValueException;

use function array_key_exists;
use function rtrim;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function trim;

class MirrorMapping
{
    protected string $url;
    protected string $path;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): MirrorMapping
    {
        $mapping = new MirrorMapping();

        // Handle different URL keys
        $url = null;
        foreach (['url', 'URL', 'source', 'SOURCE', 'origin'] as $key) {
            if (array_key_exists($key, $data) && is_string($data[$key])) {
                $url = $data[$key];
                break;
            }
        }

        // Handle different path keys
        $path = null;
        foreach (['path', 'PATH', 'target', 'TARGET', 'destination'] as $key) {
            if (array_key_exists($key, $data) && is_string($data[$key])) {
                $path = $data[$key];
                break;
            }
        }

        if ($url === null || $path === null) {
            throw new UnexpectedValueException('Missing URL or path in mirror mapping');
        }

        // Clean up URL
        $url = str_replace(['http://', 'https://'], '//', trim($url));
        if (!str_starts_with($url, '//')) {
            $url = '//' . $url;
        }
        $mapping->url = $url;

        // Clean up path
        $mapping->path = trim($path, '/');

        return $mapping;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getNormalizedUrl(): string
    {
        return sprintf('https:%s/', rtrim($this->url, '/'));
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
