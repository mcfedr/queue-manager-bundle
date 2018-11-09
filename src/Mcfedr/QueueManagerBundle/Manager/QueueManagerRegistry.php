<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Manager;

use Mcfedr\QueueManagerBundle\Queue\Job;
use Psr\Container\ContainerInterface;

class QueueManagerRegistry
{
    /**
     * @var ContainerInterface
     */
    private $queueManagers;

    /**
     * @var string
     */
    private $defaultManager;

    public function __construct(ContainerInterface $queueManagers, string $defaultManager)
    {
        $this->queueManagers = $queueManagers;
        $this->defaultManager = $defaultManager;
    }

    public function put(string $name, array $arguments = [], array $options = [], ?string $manager = null): Job
    {
        return $this->queueManagers->get($manager ?: $this->defaultManager)->put($name, $arguments, $options);
    }

    public function delete(Job $job, ?string $manager = null): void
    {
        $this->queueManagers->get($manager ?: $this->defaultManager)->delete($job);
    }
}
