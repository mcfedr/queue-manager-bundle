<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Manager;

use Mcfedr\QueueManagerBundle\Exception\WrongJobException;
use Mcfedr\QueueManagerBundle\Queue\Job;
use Psr\Container\ContainerInterface;

class QueueManagerRegistry
{
    /**
     * @var ContainerInterface
     */
    private $queueManagers;

    /**
     * @var string[]
     */
    private $managerIds;

    /**
     * @var string
     */
    private $defaultManager;

    public function __construct(ContainerInterface $queueManagers, array $managerIds, string $defaultManager)
    {
        $this->queueManagers = $queueManagers;
        $this->managerIds = $managerIds;
        $this->defaultManager = $defaultManager;
    }

    public function put(string $name, array $arguments = [], array $options = [], ?string $manager = null): Job
    {
        return $this->queueManagers->get($manager ?: $this->defaultManager)->put($name, $arguments, $options);
    }

    public function delete(Job $job, ?string $manager = null): void
    {
        if ($manager) {
            $this->queueManagers->get($manager)->delete($job);

            return;
        }

        foreach ($this->managerIds as $id) {
            try {
                $this->queueManagers->get($id)->delete($job);

                return;
            } catch (WrongJobException $e) {
            }
        }

        throw new WrongJobException('Cannot find a manager able to delete this job');
    }
}
