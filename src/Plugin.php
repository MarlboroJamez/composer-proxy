<?php

declare(strict_types=1);

namespace Molo\ComposerProxy;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider as ComposerCommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PreFileDownloadEvent;
use Composer\Repository\RepositoryInterface;
use Composer\Util\HttpDownloader;
use Exception;
use LogicException;
use Molo\ComposerProxy\Cache\PackageCache;
use Molo\ComposerProxy\Command\CommandProvider;
use Molo\ComposerProxy\Composer\ComposerFactory;
use Molo\ComposerProxy\Config\PluginConfig;
use Molo\ComposerProxy\Config\PluginConfigReader;
use Molo\ComposerProxy\Config\PluginConfigWriter;
use Molo\ComposerProxy\Config\RemoteConfig;
use Molo\ComposerProxy\Http\ParallelDownloader;
use Molo\ComposerProxy\Url\UrlMapper;
use Molo\ComposerProxy\Url\UrlPreloader;
use RuntimeException;
use UnexpectedValueException;

use function is_array;
use function sprintf;

class Plugin implements PluginInterface, EventSubscriberInterface, Capable
{
    protected const CONFIG_FILE = 'proxy.json';
    protected const REMOTE_CONFIG_URL = '%s/mirrors.json';
    protected const PLUGIN_VERSION = '1.0.0';
    protected const CACHE_TTL = 3600;
    protected const MAX_PARALLEL_DOWNLOADS = 4;

    protected static bool $enabled = true;

    protected Composer $composer;
    protected IOInterface $io;
    protected string $configPath;
    protected PluginConfig $configuration;
    protected ?UrlMapper $urlMapper = null;
    protected ?UrlPreloader $urlPreloader = null;
    protected ?HttpDownloader $httpDownloader = null;
    protected ?ParallelDownloader $parallelDownloader = null;
    protected ?PackageCache $cache = null;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
        $this->httpDownloader = $composer->getLoop()->getHttpDownloader();
        
        // Initialize cache
        $this->cache = new PackageCache(
            $composer->getConfig()->get('cache-dir')
        );

        $this->initialize();
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        static::$enabled = false;
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        if ($this->cache !== null) {
            $this->cache->clear();
        }
    }

    private function initialize(): void
    {
        try {
            $this->configPath = sprintf('%s/%s', ComposerFactory::getComposerHomeDir(), static::CONFIG_FILE);
            $this->configuration = (new PluginConfigReader())->readOrNew($this->configPath);

            static::$enabled = $this->configuration->isEnabled();
            if (!static::$enabled) {
                return;
            }

            $url = $this->configuration->getURL();
            if ($url === null) {
                throw new LogicException('Proxy enabled but no URL set');
            }

            // Try to get remote config from cache first
            $remoteConfig = null;
            if ($this->cache !== null) {
                $cachedConfig = $this->cache->get("remote_config_{$url}");
                if ($cachedConfig !== null) {
                    $remoteConfig = RemoteConfig::fromArray(json_decode($cachedConfig, true));
                }
            }

            // If not in cache, fetch and cache it
            if ($remoteConfig === null) {
                $remoteConfig = $this->getRemoteConfig($url);
                if ($this->cache !== null) {
                    $this->cache->set("remote_config_{$url}", json_encode($remoteConfig));
                }
            }

            $this->urlMapper = new UrlMapper($url, $remoteConfig->getMirrors());
            $this->urlPreloader = new UrlPreloader($this->urlMapper);
            
            // Initialize parallel downloader
            $this->parallelDownloader = new ParallelDownloader(
                $this->httpDownloader,
                $this->composer->getLoop(),
                $this->io,
                self::MAX_PARALLEL_DOWNLOADS
            );

            // Configure secure-http and protocols
            $this->configureSecureHttp();

            // Preload URLs from main repository
            if ($this->urlPreloader !== null) {
                $this->preloadRepositoryUrls($this->composer->getRepositoryManager()->getLocalRepository());
            }

        } catch (Exception $e) {
            $this->io->writeError(sprintf('<error>Failed to initialize proxy: %s</error>', $e->getMessage()));
            static::$enabled = false;
        }
    }

    protected function preloadRepositoryUrls(RepositoryInterface $repository): void
    {
        if ($this->urlPreloader === null) {
            return;
        }

        $this->io->write('<info>Preloading package URLs...</info>', true, IOInterface::VERBOSE);
        $this->urlPreloader->preloadRepository($repository);
    }

    protected function configureSecureHttp(): void
    {
        $config = $this->composer->getConfig();
        $config->merge([
            'config' => [
                'secure-http' => true,
                'github-protocols' => ['https'],
                'gitlab-protocol' => 'https',
            ]
        ]);
    }

    public function getCapabilities(): array
    {
        return [
            ComposerCommandProvider::class => CommandProvider::class,
        ];
    }

    public static function getSubscribedEvents(): array
    {
        if (!static::$enabled) {
            return [];
        }
        return [
            'pre-file-download' => ['onPreFileDownload', 0],
        ];
    }

    public function onPreFileDownload(PreFileDownloadEvent $event): void
    {
        if (!static::$enabled || $this->urlMapper === null || $this->urlPreloader === null) {
            return;
        }

        try {
            $originalUrl = $event->getProcessedUrl();
            
            // Use preloaded URL if available
            $mappedUrl = $this->urlPreloader->getUrl($originalUrl);
            
            if ($mappedUrl !== $originalUrl) {
                $this->io->write(
                    sprintf('<info>Proxy: Mapping URL %s to %s</info>', $originalUrl, $mappedUrl),
                    true,
                    IOInterface::VERBOSE
                );
            }
            
            $event->setProcessedUrl($mappedUrl);
        } catch (Exception $e) {
            $this->io->writeError(sprintf('<error>Failed to map URL: %s</error>', $e->getMessage()));
        }
    }

    public function getConfiguration(): PluginConfig
    {
        return $this->configuration;
    }

    public function writeConfiguration(PluginConfig $config): void
    {
        try {
            $writer = new PluginConfigWriter($config);
            $writer->write($this->configPath);
        } catch (Exception $e) {
            throw new RuntimeException(sprintf('Failed to write configuration: %s', $e->getMessage()));
        }
    }

    protected function getRemoteConfig(string $url): RemoteConfig
    {
        if ($this->httpDownloader === null) {
            throw new RuntimeException('HTTP downloader not initialized');
        }

        $remoteConfigUrl = sprintf(static::REMOTE_CONFIG_URL, $url);
        
        try {
            $response = $this->httpDownloader->get($remoteConfigUrl);
            
            if ($response->getStatusCode() !== 200) {
                throw new RuntimeException(
                    sprintf('Unexpected status code %d for URL %s', $response->getStatusCode(), $remoteConfigUrl)
                );
            }

            $remoteConfigData = $response->decodeJson();
            if (!is_array($remoteConfigData)) {
                throw new UnexpectedValueException('Remote configuration is formatted incorrectly');
            }

            return RemoteConfig::fromArray($remoteConfigData);
        } catch (Exception $e) {
            throw new RuntimeException(sprintf('Failed to fetch remote config: %s', $e->getMessage()));
        }
    }
}
