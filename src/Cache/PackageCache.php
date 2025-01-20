<?php

declare(strict_types=1);

namespace Molo\ComposerProxy\Cache;

use Composer\Cache as ComposerCache;
use Composer\Util\Filesystem;
use RuntimeException;

use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function sprintf;
use function time;

class PackageCache
{
    private const CACHE_VERSION = 1;
    private const CACHE_TTL = 3600; // 1 hour

    private string $cacheDir;
    private Filesystem $filesystem;

    public function __construct(string $cacheDir)
    {
        $this->cacheDir = sprintf('%s/proxy-cache-v%d', $cacheDir, self::CACHE_VERSION);
        $this->filesystem = new Filesystem();
        $this->ensureCacheDir();
    }

    public function get(string $key): ?string
    {
        $cacheFile = $this->getCacheFile($key);
        if (!file_exists($cacheFile)) {
            return null;
        }

        $data = file_get_contents($cacheFile);
        if ($data === false) {
            return null;
        }

        $cached = json_decode($data, true);
        if (!isset($cached['time']) || !isset($cached['data'])) {
            return null;
        }

        // Check if cache is still valid
        if (time() - $cached['time'] > self::CACHE_TTL) {
            $this->filesystem->unlink($cacheFile);
            return null;
        }

        return $cached['data'];
    }

    public function set(string $key, string $data): void
    {
        $cacheFile = $this->getCacheFile($key);
        $cacheData = json_encode([
            'time' => time(),
            'data' => $data,
        ]);

        if (file_put_contents($cacheFile, $cacheData) === false) {
            throw new RuntimeException(sprintf('Failed to write cache file: %s', $cacheFile));
        }
    }

    public function clear(): void
    {
        $this->filesystem->removeDirectory($this->cacheDir);
        $this->ensureCacheDir();
    }

    private function getCacheFile(string $key): string
    {
        return sprintf('%s/%s.json', $this->cacheDir, hash('sha256', $key));
    }

    private function ensureCacheDir(): void
    {
        if (!is_dir($this->cacheDir) && !mkdir($this->cacheDir, 0777, true)) {
            throw new RuntimeException(sprintf('Failed to create cache directory: %s', $this->cacheDir));
        }
    }
}
