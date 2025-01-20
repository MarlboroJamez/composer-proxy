<?php

declare(strict_types=1);

namespace Molo\ComposerProxy\Url;

use Molo\ComposerProxy\Config\MirrorMapping;

/**
 * Handles URL mapping and transformation for package downloads
 */
class UrlMapper
{
    private const GITHUB_REGEX = '#^https://api.github.com/repos/(?<package>.+)/zipball/(?<hash>[0-9a-f]+)$#i';

    /**
     * @var MirrorMapping[]
     */
    private array $mappings;
    private string $rootUrl;

    /**
     * @param MirrorMapping[] $mappings
     */
    public function __construct(string $rootUrl, array $mappings)
    {
        $this->mappings = $mappings;
        $this->rootUrl = rtrim($rootUrl, '/');
    }

    /**
     * Transform a URL using mirror mappings
     *
     * @param string $url
     * @return string
     */
    public function applyMappings(string $url): string
    {
        $patchedUrl = $this->applyGitHubShortcut($url);

        foreach ($this->mappings as $mapping) {
            $prefix = $mapping->getNormalizedUrl();
            $regex = sprintf('#^https?:%s(?<path>.+)$#i', preg_quote($prefix, '#'));
            $matches = [];
            if (preg_match($regex, $patchedUrl, $matches) === 1) {
                return sprintf(
                    '%s/%s/%s',
                    $this->rootUrl,
                    trim($mapping->getPath(), '/'),
                    ltrim($matches['path'], '/')
                );
            }
        }

        // If no mirror mapping matches, return original URL
        return $url;
    }

    /**
     * Apply GitHub API URL shortcut
     *
     * @param string $url
     * @return string
     */
    private function applyGitHubShortcut(string $url): string
    {
        $matches = [];
        if (preg_match(self::GITHUB_REGEX, $url, $matches) === 1) {
            return sprintf(
                'https://codeload.github.com/%s/legacy.zip/%s',
                $matches['package'],
                $matches['hash']
            );
        }
        return $url;
    }
}
