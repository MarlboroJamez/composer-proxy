<?php

declare(strict_types=1);

namespace Molo\ComposerProxy\Command;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Molo\ComposerProxy\Config\PluginConfig;
use Molo\ComposerProxy\Plugin;

/**
 * Command to enable the proxy
 */
class EnableCommand extends BaseCommand
{
    /**
     * Configure the command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('molo:proxy-enable')
            ->setDescription('Enable the composer proxy')
            ->setHelp('This command enables the composer proxy')
            ->addArgument(
                'url',
                InputArgument::REQUIRED,
                'The URL of the composer proxy'
            );
    }

    /**
     * Execute the command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $url = $input->getArgument('url');
        $output->writeln(sprintf('Enabling composer proxy with URL: %s', $url));

        $config = new PluginConfig();
        $config->setEnabled(true);
        $config->setProxyUrl($url);

        $pluginManager = $this->getComposer()->getPluginManager();
        $plugins = $pluginManager->getPlugins();
        
        /** @var Plugin|null $plugin */
        $plugin = null;
        foreach ($plugins as $p) {
            if ($p instanceof Plugin) {
                $plugin = $p;
                break;
            }
        }
        
        if (!$plugin) {
            $output->writeln('<error>Could not find the composer proxy plugin</error>');
            return 1;
        }

        $plugin->writeConfiguration($config);

        $output->writeln('Composer proxy enabled successfully');
        return 0;
    }

    /**
     * Get the plugin instance
     *
     * @return Plugin|null
     */
    private function getPlugin(): ?Plugin
    {
        $composer = $this->getComposer();
        $pluginManager = $composer->getPluginManager();
        $plugins = $pluginManager->getPlugins();

        foreach ($plugins as $plugin) {
            if ($plugin instanceof Plugin) {
                return $plugin;
            }
        }

        return null;
    }
}
