<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Driver;

use Mcfedr\QueueManagerBundle\Manager\QueueManager;
use Mcfedr\QueueManagerBundle\Queue\Job;
use Psr\Log\LoggerInterface;

class TestQueueManager implements QueueManager
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var array
     */
    private $options;

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
    }

    public function delete(Job $job): void
    {
        $this->info('Deleting job', [
            'name' => $job->getName(),
        ]);
    }

    protected function info(string $message, array $context): void
    {
        $this->logger->info("{$this->getLogName()}: ${message}", $context);
    }

    protected function getLogName()
    {
        return 'QueueManager';
    }
}
