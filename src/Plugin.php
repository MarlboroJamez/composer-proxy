<?php

declare(strict_types=1);

namespace Molo\ComposerProxy;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PreFileDownloadEvent;
use Exception;
use LogicException;
use Molo\ComposerProxy\Command\CommandProvider;
use Molo\ComposerProxy\Config\PluginConfig;
use Molo\ComposerProxy\Config\PluginConfigReader;
use Molo\ComposerProxy\Config\PluginConfigWriter;
use Molo\ComposerProxy\Config\RemoteConfig;
use Molo\ComposerProxy\Http\ProxyHttpDownloader;
use Molo\ComposerProxy\Url\UrlMapper;
use RuntimeException;

class Plugin implements PluginInterface, EventSubscriberInterface, Capable
{
    protected const CONFIG_FILE = 'proxy.json';
    protected const REMOTE_CONFIG_URL = '%s/mirrors.json';

    protected static bool $enabled = true;

    protected Composer $composer;
    protected IOInterface $io;
    protected string $configPath;
    protected ?PluginConfig $configuration = null;
    protected ?UrlMapper $urlMapper = null;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;

        $this->initialize();
    }

    private function initialize(): void
    {
        $this->configPath = sprintf('%s/%s', $this->composer->getConfig()->get('home'), static::CONFIG_FILE);
        $this->configuration = (new PluginConfigReader())->readOrNew($this->configPath);

        static::$enabled = $this->configuration->isEnabled();
        if (!static::$enabled) {
            return;
        }

        $url = $this->configuration->getURL();
        if ($url === null) {
            throw new LogicException('Proxy enabled but no URL set');
        }

        try {
            $remoteConfig = $this->getRemoteConfig($url);
        } catch (Exception $e) {
            $this->io->writeError(sprintf('Failed to retrieve remote config: %s', $e->getMessage()));
            static::$enabled = false;
            return;
        }

        $this->urlMapper = new UrlMapper($url, $remoteConfig->getMirrors());

        // Configure the composer instance
        $this->composer->getConfig()->merge([
            'config' => [
                'secure-http' => true,
                'github-protocols' => ['https'],
                'gitlab-protocol' => 'https',
            ]
        ]);

        // Replace the HTTP downloader
        $this->composer->setDownloader(
            new ProxyHttpDownloader(
                $this->urlMapper,
                $this->io,
                $this->composer->getConfig()
            )
        );
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'pre-file-download' => ['onPreFileDownload', 0],
        ];
    }

    public function onPreFileDownload(PreFileDownloadEvent $event): void
    {
        if (!self::$enabled || $this->urlMapper === null) {
            return;
        }

        $originalUrl = $event->getProcessedUrl();
        $newUrl = $this->urlMapper->applyMappings($originalUrl);

        if ($originalUrl !== $newUrl) {
            $this->io->debug(sprintf('Proxy: Redirecting %s to %s', $originalUrl, $newUrl));
            $event->setProcessedUrl($newUrl);
        }
    }

    public function getCapabilities(): array
    {
        return [
            CommandProviderCapability::class => CommandProvider::class,
        ];
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }

    protected function getRemoteConfig(string $url): RemoteConfig
    {
        try {
            $configUrl = sprintf(self::REMOTE_CONFIG_URL, rtrim($url, '/'));
            $response = file_get_contents($configUrl);
            if ($response === false) {
                throw new RuntimeException(sprintf('Failed to download remote config from %s', $configUrl));
            }

            $data = json_decode($response, true);
            if (!is_array($data)) {
                throw new RuntimeException('Invalid JSON in remote config');
            }

            return RemoteConfig::fromArray($data);
        } catch (Exception $e) {
            $this->io->debug(sprintf('Failed to load remote config: %s', $e->getMessage()));
            throw $e;
        }
    }
}
