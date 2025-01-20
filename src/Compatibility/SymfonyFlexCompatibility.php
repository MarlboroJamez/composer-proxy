<?php

declare(strict_types=1);

namespace Molo\ComposerProxy\Compatibility;

use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Util\HttpDownloader;
use ReflectionObject;
use RuntimeException;
use Symfony\Flex\Downloader;
use Symfony\Flex\Flex;
use Molo\ComposerProxy\Http\ProxyHttpDownloader;

/**
 * Provides compatibility with Symfony Flex
 * Ensures that Flex's parallel dist file prefetcher uses our proxy
 */
class SymfonyFlexCompatibility
{
    private IOInterface $io;

    public function __construct(IOInterface $io)
    {
        $this->io = $io;
    }

    /**
     * Apply compatibility fixes for Symfony Flex
     *
     * @param PluginInterface[] $plugins
     * @param ProxyHttpDownloader $httpDownloader
     * @return void
     */
    public function apply(array $plugins, ProxyHttpDownloader $httpDownloader): void
    {
        foreach ($plugins as $plugin) {
            if (!$plugin instanceof Flex) {
                continue;
            }

            $this->io->write(
                'Applying Symfony Flex compatibility fix',
                true,
                IOInterface::DEBUG
            );

            $this->replaceFlexDownloader($plugin, $httpDownloader);
        }
    }

    /**
     * Replace the Flex downloader with our proxy-aware version
     *
     * @param Flex $flex
     * @param ProxyHttpDownloader $httpDownloader
     * @return void
     */
    private function replaceFlexDownloader(Flex $flex, ProxyHttpDownloader $httpDownloader): void
    {
        try {
            $reflection = new ReflectionObject($flex);
            $downloaderProp = $reflection->getProperty('downloader');
            $downloaderProp->setAccessible(true);

            /** @var Downloader $downloader */
            $downloader = $downloaderProp->getValue($flex);
            $downloaderReflection = new ReflectionObject($downloader);
            
            // Replace the HttpDownloader in Flex's Downloader
            $rfsProperty = $downloaderReflection->getProperty('rfs');
            $rfsProperty->setAccessible(true);
            $rfsProperty->setValue($downloader, $httpDownloader);

        } catch (\ReflectionException $e) {
            throw new RuntimeException(
                sprintf('Failed to apply Symfony Flex compatibility fix: %s', $e->getMessage()),
                0,
                $e
            );
        }
    }
}
