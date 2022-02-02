<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Manager;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;

/**
 * @internal
 */
trait DoctrineDelayTrait
{
    private ManagerRegistry $doctrine;
    private ?string $entityManagerName;
    private ?string $defaultManager;
    private array $defaultManagerOptions = [];

    private function getEntityManager(): ObjectManager
    {
        return $this->doctrine->getManager($this->entityManagerName);
    }

    private function setOptions(array $options): void
    {
        $this->defaultManager = $options['default_manager'];
        $this->defaultManagerOptions = $options['default_manager_options'];
        $this->entityManagerName = $options['entity_manager'];
    }
}
