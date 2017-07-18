<?php

namespace Mcfedr\QueueManagerBundle\Runner;

use Mcfedr\QueueManagerBundle\Exception\InvalidWorkerException;
use Mcfedr\QueueManagerBundle\Queue\Job;
use Mcfedr\QueueManagerBundle\Queue\Worker;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class JobExecutor implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function executeJob(Job $job)
    {
        $worker = $this->container->get($job->getName());
        if (!$worker instanceof Worker) {
            throw new InvalidWorkerException("The worker {$job->getName()} is not an instance of ".Worker::class);
        }

        $worker->execute($job->getArguments());
    }
}
