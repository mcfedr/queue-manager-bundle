<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Manager;

use Mcfedr\QueueManagerBundle\Queue\Job;
use Mcfedr\QueueManagerBundle\Queue\TestJob;
use Psr\Log\LoggerInterface;

class TestQueueManager implements QueueManager
{
    private LoggerInterface $logger;

    public function __construct(array $options, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->logger->info('construct', ['options' => $options]);
    }

    public function put(string $name, array $arguments = [], array $options = []): Job
    {
        $this->logger->info('put', ['name' => $name, 'arguments' => $arguments, 'options' => $options]);

        return new TestJob($name, $arguments);
    }

    public function delete(Job $job): void
    {
        $this->logger->info('delete', ['job' => $job]);
    }
}
