<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Worker;

use Mcfedr\QueueManagerBundle\Queue\Worker;
use Psr\Log\LoggerInterface;

class TestWorker implements Worker
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function execute(array $options): void
    {
        $this->logger->info('execute', ['options' => $options]);
    }
}
