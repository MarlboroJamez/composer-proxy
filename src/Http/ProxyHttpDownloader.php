<?php

declare(strict_types=1);

namespace Molo\ComposerProxy\Http;

use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Util\HttpDownloader;
use Molo\ComposerProxy\Url\UrlMapper;

class ProxyHttpDownloader extends HttpDownloader
{
    protected UrlMapper $urlMapper;
    protected IOInterface $io;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        UrlMapper $urlMapper,
        IOInterface $io,
        Config $config,
        array $options = [],
        bool $disableTls = false
    ) {
        // Optimize for parallel downloads
        $options['max-concurrent-downloads'] = $options['max-concurrent-downloads'] ?? 8;
        $options['curl-options'] = $options['curl-options'] ?? [];
        $options['curl-options'][CURLOPT_TCP_KEEPALIVE] = 1;
        $options['curl-options'][CURLOPT_TCP_KEEPIDLE] = 60;
        $options['curl-options'][CURLOPT_TCP_KEEPINTVL] = 10;

        parent::__construct($io, $config, $options, $disableTls);
        $this->urlMapper = $urlMapper;
        $this->io = $io;
    }

    /**
     * {@inheritdoc}
     * @param string $url
     * @param array<string, mixed> $options
     */
    public function get($url, $options = [])
    {
        return parent::get($this->mapUrl($url, __METHOD__), $options);
    }

    /**
     * {@inheritdoc}
     * @param string $url
     * @param array<string, mixed> $options
     */
    public function add($url, $options = [])
    {
        return parent::add($this->mapUrl($url, __METHOD__), $options);
    }

    /**
     * {@inheritdoc}
     * @param string $url
     * @param array<string, mixed> $options
     */
    public function copy($url, $to, $options = [])
    {
        return parent::copy($this->mapUrl($url, __METHOD__), $to, $options);
    }

    /**
     * Map a URL through the URL mapper
     *
     * @param string $url
     * @param string $method
     * @return string
     */
    protected function mapUrl(string $url, string $method): string
    {
        $mappedUrl = $this->urlMapper->applyMappings($url);
        if ($mappedUrl !== $url) {
            $this->io->debug(sprintf('%s(url=%s): mapped to %s', $method, $url, $mappedUrl));
        }
        return $mappedUrl;
    }
}
