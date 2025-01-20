<?php

declare(strict_types=1);

namespace Molo\ComposerProxy\Command;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;

/**
 * Provides the commands for the plugin
 */
class CommandProvider implements CommandProviderCapability
{
    /**
     * Returns an array of command instances
     *
     * @return \Composer\Command\BaseCommand[]
     */
    public function getCommands(): array
    {
        return [
            new EnableCommand(),
            new DisableCommand(),
        ];
    }
}
