<?php

declare(strict_types=1);

namespace Molo\ComposerProxy\Compatibility;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Installer\PackageEvent;
use Composer\Plugin\PluginInterface;
use Molo\ComposerProxy\Http\ProxyHttpDownloader;
use Molo\ComposerProxy\Composer\OperationAdapter;

/**
 * Detects and fixes compatibility issues with other plugins
 */
class CompatibilityDetector
{
    private Composer $composer;
    private IOInterface $io;
    private ProxyHttpDownloader $httpDownloader;
    private SymfonyFlexCompatibility $symfonyFlexCompatibility;

    public function __construct(
        Composer $composer,
        IOInterface $io,
        ProxyHttpDownloader $httpDownloader
    ) {
        $this->composer = $composer;
        $this->io = $io;
        $this->httpDownloader = $httpDownloader;
        $this->symfonyFlexCompatibility = new SymfonyFlexCompatibility($io);
    }

    /**
     * Fix compatibility with other plugins
     */
    public function fixPluginCompatibility(): void
    {
        $plugins = $this->composer->getPluginManager()->getPlugins();
        $this->symfonyFlexCompatibility->apply($plugins, $this->httpDownloader);
    }

    /**
     * Handle package installation event
     *
     * @param PackageEvent $event
     */
    public function onPackageInstall(PackageEvent $event): void
    {
        $operation = new OperationAdapter($event->getOperation());
        $package = $operation->getPackage();

        if ($package->getType() === 'symfony-flex') {
            $this->io->write(
                'Detected Symfony Flex installation, applying compatibility fix',
                true,
                IOInterface::DEBUG
            );
            $this->fixPluginCompatibility();
        }
    }
}
