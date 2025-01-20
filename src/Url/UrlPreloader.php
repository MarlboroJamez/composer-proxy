<?php

declare(strict_types=1);

namespace Molo\ComposerProxy\Url;

use Composer\Package\PackageInterface;
use Composer\Repository\RepositoryInterface;

class UrlPreloader
{
    private UrlMapper $urlMapper;
    private array $preloadedUrls = [];

    public function __construct(UrlMapper $urlMapper)
    {
        $this->urlMapper = $urlMapper;
    }

    /**
     * Pre-maps URLs for all packages in a repository to avoid doing it on-demand
     */
    public function preloadRepository(RepositoryInterface $repository): void
    {
        foreach ($repository->getPackages() as $package) {
            $this->preloadPackage($package);
        }
    }

    /**
     * Pre-maps URLs for a specific package
     */
    public function preloadPackage(PackageInterface $package): void
    {
        // Preload dist URL
        $distUrl = $package->getDistUrl();
        if ($distUrl) {
            $this->preloadedUrls[$distUrl] = $this->urlMapper->applyMappings($distUrl);
        }

        // Preload source URL
        $sourceUrl = $package->getSourceUrl();
        if ($sourceUrl) {
            $this->preloadedUrls[$sourceUrl] = $this->urlMapper->applyMappings($sourceUrl);
        }
    }

    /**
     * Gets a pre-mapped URL if available, otherwise maps it on demand
     */
    public function getUrl(string $url): string
    {
        return $this->preloadedUrls[$url] ?? $this->urlMapper->applyMappings($url);
    }

    /**
     * Clears the preloaded URL cache
     */
    public function clear(): void
    {
        $this->preloadedUrls = [];
    }
}
