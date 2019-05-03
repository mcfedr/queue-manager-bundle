<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Queue;

use Mcfedr\QueueManagerBundle\Entity\DoctrineDelayJob;
use Mcfedr\QueueManagerBundle\Worker\DoctrineDelayWorker;

class DoctrineDelayWorkerJob implements RetryableJob
{
    /**
     * @var DoctrineDelayJob
     */
    private $delayJob;

    public function __construct(DoctrineDelayJob $delayJob)
    {
        $this->delayJob = $delayJob;
    }

    public function getDelayJob(): DoctrineDelayJob
    {
        return $this->delayJob;
    }

    public function getName(): string
    {
        return DoctrineDelayWorker::class;
    }

    public function getArguments(): array
    {
        return [
            'job' => $this->delayJob,
        ];
    }

    public function getRetryCount(): int
    {
        return $this->getDelayJob()->getRetryCount();
    }
}
