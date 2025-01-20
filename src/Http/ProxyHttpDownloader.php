<?php

declare(strict_types=1);

namespace Molo\ComposerProxy\Http;

use Composer\Util\HttpDownloader;
use Composer\Util\Http\Response;
use Composer\IO\IOInterface;
use Composer\Config;
use Molo\ComposerProxy\Url\UrlMapper;

class ProxyHttpDownloader extends HttpDownloader
{
    private UrlMapper $urlMapper;
    private array $transformedUrls = [];

    public function __construct(
        UrlMapper $urlMapper,
        IOInterface $io,
        Config $config,
        array $options = [],
        bool $disableTls = false,
        array $streamOptions = []
    ) {
        // Optimize for parallel downloads
        $options['max-concurrent-downloads'] = $options['max-concurrent-downloads'] ?? 8;
        $options['curl-options'] = $options['curl-options'] ?? [];
        $options['curl-options'][CURLOPT_TCP_KEEPALIVE] = 1;
        $options['curl-options'][CURLOPT_TCP_KEEPIDLE] = 60;
        $options['curl-options'][CURLOPT_TCP_KEEPINTVL] = 10;
        
        parent::__construct($io, $config, $options, $disableTls, $streamOptions);
        $this->urlMapper = $urlMapper;
    }

    public function get($url, $options = []): Response
    {
        $transformedUrl = $this->getTransformedUrl($url);
        return parent::get($transformedUrl, $this->optimizeOptions($options));
    }

    public function copy($url, $to, $options = []): Response
    {
        $transformedUrl = $this->getTransformedUrl($url);
        return parent::copy($transformedUrl, $to, $this->optimizeOptions($options));
    }

    private function getTransformedUrl(string $url): string
    {
        // Cache transformed URLs to avoid repeated transformations
        if (!isset($this->transformedUrls[$url])) {
            $this->transformedUrls[$url] = $this->urlMapper->transformUrl($url);
        }
        return $this->transformedUrls[$url];
    }

    private function optimizeOptions(array $options): array
    {
        // Add optimized curl options
        $options['curl-options'] = ($options['curl-options'] ?? []) + [
            // Enable HTTP/2 support
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
            // Enable connection reuse
            CURLOPT_FORBID_REUSE => false,
            CURLOPT_FRESH_CONNECT => false,
            // Optimize TCP settings
            CURLOPT_TCP_NODELAY => 1,
        ];

        // Add cache headers
        $options['http'] = ($options['http'] ?? []) + [
            'header' => array_merge(
                $options['http']['header'] ?? [],
                [
                    'Cache-Control: max-age=31536000',
                    'If-None-Match: *',
                    'If-Modified-Since: ' . gmdate('D, d M Y H:i:s', time() - 3600) . ' GMT'
                ]
            )
        ];
        
        return $options;
    }
}
