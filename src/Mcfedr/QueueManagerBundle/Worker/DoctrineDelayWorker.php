<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Worker;

use Mcfedr\QueueManagerBundle\Entity\DoctrineDelayJob;
use Mcfedr\QueueManagerBundle\Exception\UnrecoverableJobException;
use Mcfedr\QueueManagerBundle\Manager\QueueManagerRegistry;
use Mcfedr\QueueManagerBundle\Queue\InternalWorker;

class DoctrineDelayWorker implements InternalWorker
{
    /**
     * @var QueueManagerRegistry
     */
    private $queueManagerRegistry;

    public function __construct(QueueManagerRegistry $queueManagerRegistry)
    {
        $this->queueManagerRegistry = $queueManagerRegistry;
    }

    /**
     * Called to start the queued task.
     *
     * @throws \Exception
     * @throws UnrecoverableJobException
     */
    public function execute(array $arguments): void
    {
        if (!isset($arguments['job'])) {
            throw new UnrecoverableJobException('Missing doctrine delay job');
        }

        $job = $arguments['job'];
        if (!$job instanceof DoctrineDelayJob) {
            throw new UnrecoverableJobException('Invalid job');
        }

        $this->queueManagerRegistry->put($job->getName(), $job->getArguments(), $job->getOptions(), $job->getManager());
    }
}
