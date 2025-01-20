<?php

declare(strict_types=1);

namespace Molo\ComposerProxy\Http;

use Composer\Config;
use Composer\Downloader\TransportException;
use Composer\IO\IOInterface;
use Composer\Util\Http\Response;
use Composer\Util\Platform;
use Composer\Util\HttpDownloader;
use Molo\ComposerProxy\Url\UrlMapper;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

use function array_key_exists;
use function count;
use function sprintf;

class ParallelProxyDownloader extends HttpDownloader
{
    private UrlMapper $urlMapper;
    private array $activeJobs = [];
    private array $jobsByHash = [];

    public function __construct(UrlMapper $urlMapper, IOInterface $io, Config $config, array $options = [], array $retryAuthFailure = [])
    {
        parent::__construct($io, $config, $options, $retryAuthFailure);
        $this->urlMapper = $urlMapper;
    }

    public function add(string $url, array $options = []): PromiseInterface
    {
        $mappedUrl = $this->urlMapper->applyMappings($url);
        $hash = hash('sha256', $mappedUrl);

        // If we already have a job for this URL, return its promise
        if (array_key_exists($hash, $this->jobsByHash)) {
            return $this->jobsByHash[$hash];
        }

        // Create a new promise for this download
        $promise = new Promise(function ($resolve, $reject) use ($mappedUrl, $options, $hash) {
            try {
                $response = parent::get($mappedUrl, $options);
                $resolve($response);
            } catch (TransportException $e) {
                $reject($e);
            } finally {
                unset($this->activeJobs[$hash]);
                unset($this->jobsByHash[$hash]);
            }
        });

        $this->activeJobs[$hash] = true;
        $this->jobsByHash[$hash] = $promise;

        return $promise;
    }

    public function copy(string $url, string $to, array $options = []): PromiseInterface
    {
        $mappedUrl = $this->urlMapper->applyMappings($url);
        return parent::copy($mappedUrl, $to, $options);
    }

    public function get(string $url, array $options = []): Response
    {
        $mappedUrl = $this->urlMapper->applyMappings($url);
        return parent::get($mappedUrl, $options);
    }

    public function getAsync(string $url, array $options = []): PromiseInterface
    {
        return $this->add($url, $options);
    }

    public function wait(): void
    {
        while (count($this->activeJobs) > 0) {
            Platform::sleep(0.05);
        }
    }
}
