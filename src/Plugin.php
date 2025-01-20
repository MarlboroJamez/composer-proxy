<?php

declare(strict_types=1);

namespace Molo\ComposerProxy;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\Capable;
use Composer\Plugin\Capability\CommandProvider as ComposerCommandProvider;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PreFileDownloadEvent;
use Composer\Util\HttpDownloader;
use LogicException;
use RuntimeException;
use UnexpectedValueException;
use Molo\ComposerProxy\Command\CommandProvider;
use Molo\ComposerProxy\Config\PluginConfig;
use Molo\ComposerProxy\Config\PluginConfigReader;
use Molo\ComposerProxy\Config\PluginConfigWriter;
use Molo\ComposerProxy\Config\RemoteConfig;
use Molo\ComposerProxy\Url\UrlMapper;
use Molo\ComposerProxy\Http\ProxyHttpDownloader;
use Molo\ComposerProxy\Compatibility\CompatibilityDetector;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;

/**
 * Main plugin class that integrates with Composer
 */
class Plugin implements PluginInterface, EventSubscriberInterface, Capable
{
    protected static bool $enabled = true;

    protected const CONFIG_FILE = 'proxy.json';
    protected const REMOTE_CONFIG_URL = '%s/mirrors.json';

    private Composer $composer;
    private IOInterface $io;
    private string $configPath = '';
    private ?PluginConfig $config = null;
    private ?UrlMapper $urlMapper = null;
    private ?ProxyHttpDownloader $httpDownloader = null;
    private ?CompatibilityDetector $compatibilityDetector = null;

    /**
     * Apply plugin modifications to Composer
     *
     * @param Composer $composer
     * @param IOInterface $io
     * @return void
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
        $this->configPath = sprintf('%s/%s', $composer->getConfig()->get('home'), static::CONFIG_FILE);
        
        // Enable Composer 2.x optimizations
        if (method_exists($composer->getConfig(), 'set')) {
            $composer->getConfig()->set('use-github-api', false); // Prefer direct downloads
            $composer->getConfig()->set('optimize-autoloader', true);
            $composer->getConfig()->set('classmap-authoritative', true);
            $composer->getConfig()->set('apcu-autoloader', true);
            $composer->getConfig()->set('preferred-install', 'dist');
        }
        
        // Initialize the plugin
        $this->initialize();
        
        if (static::$enabled) {
            // Initialize compatibility detector
            $this->compatibilityDetector = new CompatibilityDetector(
                $this->composer,
                $this->io,
                $this->getHttpDownloader()
            );
            
            // Apply initial compatibility fixes
            $this->compatibilityDetector->fixPluginCompatibility();
        }
    }

    /**
     * Remove any hooks from Composer
     *
     * @param Composer $composer
     * @param IOInterface $io
     * @return void
     */
    public function deactivate(Composer $composer, IOInterface $io): void
    {
        static::$enabled = false;
    }

    /**
     * Prepare the plugin to be uninstalled
     *
     * @param Composer $composer
     * @param IOInterface $io
     * @return void
     */
    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // Clean up any resources if needed
    }

    private function initialize(): void
    {
        $configReader = new PluginConfigReader($this->composer);
        $this->config = $configReader->readOrNew($this->configPath);
        
        static::$enabled = $this->config->isEnabled();
        if (!static::$enabled) {
            return;
        }

        $proxyUrl = $this->config->getProxyUrl();
        if ($proxyUrl === null) {
            throw new LogicException('Proxy enabled but no URL set');
        }

        try {
            $remoteConfig = $this->getRemoteConfig($proxyUrl);
            if (static::$enabled) {
                $this->urlMapper = new UrlMapper(
                    rtrim($proxyUrl, '/'),
                    $remoteConfig->getMirrors()
                );
            }
        } catch (\Exception $e) {
            $this->io->writeError(sprintf('Failed to retrieve remote config: %s', $e->getMessage()));
            static::$enabled = false;
        }
    }

    protected function getRemoteConfig(string $proxyUrl): RemoteConfig
    {
        $httpDownloader = $this->composer->getLoop()->getHttpDownloader();
        $remoteConfigUrl = sprintf(static::REMOTE_CONFIG_URL, $proxyUrl);
        
        try {
            $response = $httpDownloader->get($remoteConfigUrl);
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
        } catch (\Exception $e) {
            $this->io->writeError(sprintf('Failed to retrieve remote config from %s: %s', $remoteConfigUrl, $e->getMessage()));
            throw $e;
        }
    }

    private function getHttpDownloader(): ProxyHttpDownloader
    {
        if ($this->httpDownloader === null) {
            if ($this->urlMapper === null) {
                throw new \LogicException('URL mapper must be initialized before creating HTTP downloader');
            }
            
            $this->httpDownloader = new ProxyHttpDownloader(
                $this->urlMapper,
                $this->io,
                $this->composer->getConfig()
            );
        }
        
        return $this->httpDownloader;
    }

    /**
     * Returns an array of event names this subscriber wants to listen to
     *
     * @return array<string, array{string, int}>
     */
    public static function getSubscribedEvents(): array
    {
        if (!static::$enabled) {
            return [];
        }

        return [
            PluginEvents::PRE_FILE_DOWNLOAD => ['onPreFileDownload', 0],
            PackageEvents::POST_PACKAGE_INSTALL => ['onPostPackageInstall', 0],
            PluginEvents::PRE_COMMAND_RUN => ['onPreCommandRun', PHP_INT_MAX],
        ];
    }

    /**
     * Handle pre-file download event
     * Transform URLs to use the proxy
     *
     * @param \Composer\Plugin\PreFileDownloadEvent $event
     * @return void
     */
    public function onPreFileDownload(\Composer\Plugin\PreFileDownloadEvent $event): void
    {
        $originalUrl = $event->getProcessedUrl();
        $mappedUrl = $this->urlMapper->transformUrl($originalUrl);
        
        if ($mappedUrl !== $originalUrl) {
            $this->io->write(
                sprintf('%s(url=%s): mapped to %s', __METHOD__, $originalUrl, $mappedUrl),
                true,
                IOInterface::DEBUG
            );
        }
        
        $event->setProcessedUrl($mappedUrl);
    }

    /**
     * Handle post package install event
     *
     * @param PackageEvent $event
     * @return void
     */
    public function onPostPackageInstall(PackageEvent $event): void
    {
        if ($this->compatibilityDetector !== null) {
            $this->compatibilityDetector->onPackageInstall($event);
        }
    }

    /**
     * Handle pre command run event
     *
     * @return void
     */
    public function onPreCommandRun(): void
    {
        if ($this->compatibilityDetector !== null) {
            $this->compatibilityDetector->fixPluginCompatibility();
        }
    }

    public function getCapabilities(): array
    {
        return [
            ComposerCommandProvider::class => CommandProvider::class,
        ];
    }

    public function writeConfiguration(PluginConfig $config): void
    {
        $writer = new PluginConfigWriter($this->composer);
        $writer->write($config);
    }
}
