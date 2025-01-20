<?php

declare(strict_types=1);

namespace Molo\ComposerProxy\Composer;

use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\OperationInterface;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Package\PackageInterface;

/**
 * Adapts Composer operations to a common interface
 */
class OperationAdapter
{
    private OperationInterface $operation;

    public function __construct(OperationInterface $operation)
    {
        $this->operation = $operation;
    }

    /**
     * Get the package being operated on
     *
     * @return PackageInterface
     */
    public function getPackage(): PackageInterface
    {
        if ($this->operation instanceof InstallOperation) {
            return $this->operation->getPackage();
        }

        if ($this->operation instanceof UpdateOperation) {
            return $this->operation->getTargetPackage();
        }

        if ($this->operation instanceof UninstallOperation) {
            return $this->operation->getPackage();
        }

        throw new \RuntimeException(
            sprintf('Unsupported operation type: %s', get_class($this->operation))
        );
    }
}
