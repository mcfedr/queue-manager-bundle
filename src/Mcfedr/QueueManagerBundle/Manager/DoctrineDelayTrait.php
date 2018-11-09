<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Manager;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManager;

/**
 * @internal
 */
trait DoctrineDelayTrait
{
    /**
     * @var ManagerRegistry
     */
    private $doctrine;

    /**
     * @var string
     */
    private $entityManagerName;

    /**
     * @var string
     */
    private $defaultManager;

    /**
     * @var array
     */
    private $defaultManagerOptions = [];

    private function getEntityManager(): EntityManager
    {
        return $this->doctrine->getManager($this->entityManagerName);
    }

    private function setOptions(array $options)
    {
        $this->defaultManager = $options['default_manager'];
        $this->defaultManagerOptions = $options['default_manager_options'];
        $this->entityManagerName = $options['entity_manager'];
    }
}
