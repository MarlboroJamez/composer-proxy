<?php

declare(strict_types=1);

namespace Molo\ComposerProxy\Command;

use Composer\Command\BaseCommand;
use Molo\ComposerProxy\Plugin;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to disable the proxy
 */
class DisableCommand extends BaseCommand
{
    protected Plugin $plugin;

    public function __construct(Plugin $plugin)
    {
        parent::__construct();
        $this->plugin = $plugin;
    }

    /**
     * Configure the command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('molo:proxy-disable')
            ->setDescription('Disables the Composer proxy plugin')
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
        // Update configuration
        $config = $this->plugin->getConfiguration();
        $config->setEnabled(false);

        // Write new configuration
        $this->plugin->writeConfiguration($config);

        $output->writeln('Composer proxy is now <info>disabled</info>.');
        return 0;
    }
}
