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
use Molo\ComposerProxy\Composer\ComposerFactory;
use Molo\ComposerProxy\Config\PluginConfig;
use Molo\ComposerProxy\Config\PluginConfigReader;
use Molo\ComposerProxy\Config\PluginConfigWriter;
use Molo\ComposerProxy\Config\RemoteConfig;
use Molo\ComposerProxy\Http\ParallelProxyDownloader;
use Molo\ComposerProxy\Url\UrlMapper;
use RuntimeException;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use UnexpectedValueException;

use function is_array;
use function sprintf;

class Plugin implements PluginInterface, EventSubscriberInterface, Capable
{
    protected const CONFIG_FILE = 'proxy.json';
    protected const REMOTE_CONFIG_URL = '%s/mirrors.json';

    protected static bool $enabled = true;

    protected Composer $composer;
    protected IOInterface $io;
    protected string $configPath;
    protected PluginConfig $configuration;
    protected UrlMapper $urlMapper;
    protected ?ParallelProxyDownloader $downloader = null;
    protected ?OutputInterface $output = null;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
        $this->output = new ConsoleOutput();

        $this->initialize();
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        if ($this->downloader !== null) {
            $this->downloader->wait();
        }
        static::$enabled = false;
    }

    private function initialize(): void
    {
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

        // Create and configure the parallel downloader
        $this->downloader = new ParallelProxyDownloader(
            $this->urlMapper,
            $this->io,
            $this->composer->getConfig(),
            ['ssl' => ['verify_peer' => true]]
        );

        // Replace the HTTP downloader
        $this->composer->getLoop()->setHttpDownloader($this->downloader);
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        if ($this->downloader !== null) {
            $this->downloader->wait();
        }
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
        if (!static::$enabled || $this->urlMapper === null) {
            return;
        }

        $originalUrl = $event->getProcessedUrl();
        $mappedUrl = $this->urlMapper->applyMappings($originalUrl);
        if ($mappedUrl !== $originalUrl) {
            $this->io->write(
                sprintf('%s(url=%s): mapped to %s', __METHOD__, $originalUrl, $mappedUrl),
                true,
                IOInterface::DEBUG
            );
        }
        $event->setProcessedUrl($mappedUrl);
    }

    public function getConfiguration(): PluginConfig
    {
        return $this->configuration;
    }

    public function writeConfiguration(PluginConfig $config): void
    {
        $writer = new PluginConfigWriter($config);
        $writer->write($this->configPath);
    }

    protected function getRemoteConfig(string $url): RemoteConfig
    {
        if ($this->downloader === null) {
            $this->downloader = new ParallelProxyDownloader(
                $this->urlMapper ?? new UrlMapper($url, []),
                $this->io,
                $this->composer->getConfig(),
                ['ssl' => ['verify_peer' => true]]
            );
        }

        $remoteConfigUrl = sprintf(static::REMOTE_CONFIG_URL, $url);
        $response = $this->downloader->get($remoteConfigUrl);
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
    }
}
