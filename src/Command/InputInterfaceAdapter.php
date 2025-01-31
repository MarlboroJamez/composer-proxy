<?php

declare(strict_types=1);

namespace Molo\ComposerProxy\Command;

use Symfony\Component\Console\Input\InputInterface;
use UnexpectedValueException;

use function is_string;
use function sprintf;

class InputInterfaceAdapter
{
    private InputInterface $input;

    public function __construct(InputInterface $input)
    {
        $this->input = $input;
    }

    public function getStringArgument(string $name): ?string
    {
        $value = $this->input->getArgument($name);
        if ($value !== null && !is_string($value)) {
            throw new UnexpectedValueException(
                sprintf('Unexpected type for input argument "%s"', $name)
            );
        }
        return $value;
    }
}
