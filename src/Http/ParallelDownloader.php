<?php

declare(strict_types=1);

namespace Molo\ComposerProxy\Http;

use Composer\Util\Http\Response;
use Composer\Util\Loop;
use Composer\Util\HttpDownloader;
use Composer\IO\IOInterface;
use Exception;

class ParallelDownloader
{
    private HttpDownloader $httpDownloader;
    private Loop $loop;
    private IOInterface $io;
    private array $promises = [];
    private array $results = [];
    private int $concurrency;

    public function __construct(HttpDownloader $httpDownloader, Loop $loop, IOInterface $io, int $concurrency = 4)
    {
        $this->httpDownloader = $httpDownloader;
        $this->loop = $loop;
        $this->io = $io;
        $this->concurrency = $concurrency;
    }

    /**
     * @param array<string, string> $urls Map of package names to URLs
     * @return array<string, Response>
     */
    public function downloadAll(array $urls): array
    {
        $active = 0;
        $urlsIterator = new \ArrayIterator($urls);

        while ($urlsIterator->valid() || $active > 0) {
            // Start new downloads if we have capacity
            while ($urlsIterator->valid() && $active < $this->concurrency) {
                $packageName = $urlsIterator->key();
                $url = $urlsIterator->current();

                $this->promises[$packageName] = $this->httpDownloader->add($url);
                $active++;
                $urlsIterator->next();
            }

            // Wait for any download to complete
            if ($active > 0) {
                $this->loop->wait([
                    'promise' => true,
                    'downloadProgress' => function ($packageName, $progress, $total) {
                        if ($total > 0) {
                            $this->io->overwriteError(sprintf(
                                "Downloading %s (%d%%)",
                                $packageName,
                                $progress / $total * 100
                            ), false);
                        }
                    },
                ]);

                // Check for completed downloads
                foreach ($this->promises as $packageName => $promise) {
                    if ($promise->isDone()) {
                        try {
                            $this->results[$packageName] = $promise->getResponse();
                        } catch (Exception $e) {
                            $this->io->writeError(sprintf('<error>Failed to download %s: %s</error>', $packageName, $e->getMessage()));
                        }
                        unset($this->promises[$packageName]);
                        $active--;
                    }
                }
            }
        }

        return $this->results;
    }
}
