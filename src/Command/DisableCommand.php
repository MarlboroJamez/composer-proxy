<?php

declare(strict_types=1);

namespace Molo\ComposerProxy\Command;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Molo\ComposerProxy\Config\PluginConfig;
use Molo\ComposerProxy\Plugin;

/**
 * Command to disable the proxy
 */
class DisableCommand extends BaseCommand
{
    /**
     * Configure the command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('molo:proxy-disable')
            ->setDescription('Disable the composer proxy')
            ->setHelp('This command disables the composer proxy');
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
        $composer = $this->getComposer();
        $plugin = $this->getPlugin();
        
        if (!$plugin) {
            $output->writeln('<error>Could not find the composer proxy plugin</error>');
            return 1;
        }

        $config = $plugin->getConfig() ?? new PluginConfig();
        $config->setEnabled(false);

        if (!$plugin->setConfig($config)) {
            $output->writeln('<error>Failed to save configuration</error>');
            return 1;
        }

        $output->writeln('<info>Composer proxy has been disabled.</info>');
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
