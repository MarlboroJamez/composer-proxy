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
use Exception;
use LogicException;
use Molo\ComposerProxy\Command\CommandProvider;
use Molo\ComposerProxy\Config\PluginConfig;
use Molo\ComposerProxy\Config\PluginConfigReader;
use Molo\ComposerProxy\Config\RemoteConfig;
use Molo\ComposerProxy\Http\ProxyHttpDownloader;
use Molo\ComposerProxy\Url\UrlMapper;

use function array_key_exists;
use function file_get_contents;
use function is_array;
use function json_decode;
use function rtrim;
use function sprintf;

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
            'pre-file-download' => [
                ['onPreFileDownload', 0]
            ],
        ];
    }

    public function onPreFileDownload(PreFileDownloadEvent $event): void
    {
        if (!static::$enabled || $this->urlMapper === null) {
            return;
        }

        $processedUrl = $this->urlMapper->getMirroredUrl($event->getProcessedUrl());
        if ($processedUrl !== null) {
            $event->setProcessedUrl($processedUrl);
        }
    }

    public function getCapabilities(): array
    {
        return [
            ComposerCommandProvider::class => CommandProvider::class,
        ];
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        static::$enabled = false;
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }

    protected function getRemoteConfig(string $url): RemoteConfig
    {
        try {
            $configUrl = sprintf(self::REMOTE_CONFIG_URL, rtrim($url, '/'));
            $data = @file_get_contents($configUrl);
            if ($data === false) {
                throw new RuntimeException('Failed to download remote configuration');
            }

            $data = json_decode($data, true);
            if (!is_array($data)) {
                throw new RuntimeException('Invalid remote configuration format');
            }

            return RemoteConfig::fromArray($data);
        } catch (Exception $e) {
            $this->io->debug(sprintf('Failed to load remote config: %s', $e->getMessage()));
            throw $e;
        }
    }

    public function getConfiguration(): PluginConfig
    {
        return $this->configuration ?? new PluginConfig();
    }

    public function writeConfiguration(PluginConfig $config): void
    {
        try {
            $config->validate();
            
            $data = [
                'enabled' => $config->isEnabled(),
                'url' => $config->getURL(),
            ];

            $configPath = sprintf('%s/%s', $this->composer->getConfig()->get('home'), static::CONFIG_FILE);
            if (file_put_contents($configPath, json_encode($data, JSON_PRETTY_PRINT)) === false) {
                throw new RuntimeException('Failed to write configuration');
            }

            $this->configuration = $config;
            static::$enabled = $config->isEnabled();
        } catch (Exception $e) {
            $this->io->writeError(sprintf('<error>Failed to write configuration: %s</error>', $e->getMessage()));
            throw $e;
        }
    }
}
