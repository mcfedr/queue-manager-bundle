<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Driver;

use Mcfedr\QueueManagerBundle\Manager\QueueManager;
use Mcfedr\QueueManagerBundle\Queue\Job;
use Mcfedr\QueueManagerBundle\Queue\TestJob;
use Psr\Log\LoggerInterface;

class TestQueueManager implements QueueManager
{
    private LoggerInterface $logger;
    private array $options;

    public function __construct(LoggerInterface $logger, array $options)
    {
        $this->logger = $logger;
        $this->options = $options;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function put(string $name, array $arguments = [], array $options = []): Job
    {
        $this->info('Putting new job', [
            'name' => $name,
        ]);

        return new TestJob($name, $arguments);
    }

    public function delete(Job $job): void
    {
        $this->info('Deleting job', [
            'name' => $job->getName(),
        ]);
    }

    protected function info(string $message, array $context): void
    {
        $this->logger->info("{$this->getLogName()}: {$message}", $context);
    }

    protected function getLogName(): string
    {
        return 'QueueManager';
    }
}
