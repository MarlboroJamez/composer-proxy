<?php

declare(strict_types=1);

namespace Molo\ComposerProxy\Test\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Molo\ComposerProxy\Command\EnableCommand;

class EnableCommandTest extends TestCase
{
    public function testExecute(): void
    {
        $command = new EnableCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $this->assertStringContainsString(
            'Composer proxy has been enabled',
            $commandTester->getDisplay()
        );
    }
}
