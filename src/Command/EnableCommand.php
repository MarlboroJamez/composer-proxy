<?php

declare(strict_types=1);

namespace Molo\ComposerProxy\Command;

use Composer\Command\BaseCommand;
use Molo\ComposerProxy\Plugin;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to enable the proxy
 */
class EnableCommand extends BaseCommand
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
            ->setName('molo:proxy-enable')
            ->setDescription('Enables the Composer proxy plugin')
            ->addArgument('url', InputArgument::OPTIONAL, 'Sets the URL to your proxy instance');
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
        if ($url !== null) {
            $output->writeln(sprintf('Enabling composer proxy with URL: %s', $url));
        }

        // Update configuration
        $config = $this->plugin->getConfiguration();
        $config->setEnabled(true);
        if ($url !== null) {
            $config->setURL($url);
        }

        // Write new configuration
        $this->plugin->writeConfiguration($config);

        $output->writeln('Composer proxy is now <info>enabled</info>.');
        return 0;
    }
}
