<?php

declare(strict_types=1);

namespace Molo\ComposerProxy\Command;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use Molo\ComposerProxy\Plugin;

class CommandProvider implements CommandProviderCapability
{
    protected Plugin $plugin;

    /**
     * @param array{plugin: Plugin} $arguments
     */
    public function __construct(array $arguments)
    {
        $this->plugin = $arguments['plugin'];
    }

    public function getCommands(): array
    {
        return [
            new EnableCommand($this->plugin),
            new DisableCommand($this->plugin),
        ];
    }
}
