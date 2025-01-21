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
use Composer\Util\HttpDownloader;
use Exception;
use LogicException;
use Molo\ComposerProxy\Command\CommandProvider;
use Molo\ComposerProxy\Composer\ComposerFactory;
use Molo\ComposerProxy\Config\AuthConfig;
use Molo\ComposerProxy\Config\PluginConfig;
use Molo\ComposerProxy\Config\PluginConfigReader;
use Molo\ComposerProxy\Config\PluginConfigWriter;
use Molo\ComposerProxy\Config\RemoteConfig;
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
    protected ?HttpDownloader $httpDownloader = null;
    protected ?OutputInterface $output = null;
    protected ?AuthConfig $authConfig = null;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
        $this->output = new ConsoleOutput();
        $this->httpDownloader = $composer->getLoop()->getHttpDownloader();
        $this->authConfig = new AuthConfig($composer->getConfig(), $io);

        $this->initialize();
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
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
            
            // Show proxy status message
            if ($this->io->isVerbose()) {
                $authStatus = $this->authConfig->hasAuthFor($url) ? 
                    '<info>✓ authenticated</info>' : 
                    '<comment>! not authenticated</comment>';
                
                $this->io->write(sprintf(
                    '  <info>Composer Proxy:</info> %s [%s]',
                    $url,
                    $authStatus
                ));
            }
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
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
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
            // Add authentication options
            if ($this->authConfig !== null) {
                $options = $this->authConfig->getAuthOptions($mappedUrl);
                if (!empty($options['http']['header'])) {
                    $event->setProcessedUrl(
                        $mappedUrl,
                        $options['http']['header']
                    );
                    
                    if ($this->io->isVeryVerbose()) {
                        $this->io->write(
                            sprintf('  <info>Proxy:</info> %s → %s (authenticated)', $originalUrl, $mappedUrl),
                            true,
                            IOInterface::VERBOSE
                        );
                        return;
                    }
                }
            }

            if ($this->io->isVeryVerbose()) {
                $this->io->write(
                    sprintf('  <info>Proxy:</info> %s → %s', $originalUrl, $mappedUrl),
                    true,
                    IOInterface::VERBOSE
                );
            }
            
            $event->setProcessedUrl($mappedUrl);
        }
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
        if ($this->httpDownloader === null) {
            throw new RuntimeException('HTTP downloader not initialized');
        }

        $remoteConfigUrl = sprintf(static::REMOTE_CONFIG_URL, $url);
        
        // Add authentication options for the remote config request
        $options = [];
        if ($this->authConfig !== null) {
            $options = $this->authConfig->getAuthOptions($remoteConfigUrl);
        }

        $response = $this->httpDownloader->get($remoteConfigUrl, $options);
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
